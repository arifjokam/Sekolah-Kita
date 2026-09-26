<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\NotificationCategory;
use App\Enums\ScheduleStatus;
use App\Enums\WeekDay;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\ClassSchedule;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use App\Models\User;
use App\Notifications\ScheduleChangedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Administrator write path for the weekly class schedule.
 *
 * Three conflicts are refused, and all three are refused *inside the write path*
 * rather than only in the form, so an API-style request cannot bypass them:
 *
 *  1. a class cannot be in two places at once;
 *  2. a teacher cannot teach two classes at once;
 *  3. a room cannot host two lessons at once.
 *
 * The scope integrity rules (the term must belong to the class's academic year,
 * the class and teacher must actually be connected in the same semester) live
 * here too, because a schedule row that crosses academic scopes would silently
 * corrupt every downstream read.
 */
class ScheduleAdministrationService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array{search?: string|null, academic_year_id?: int|null, term_id?: int|null, class_id?: int|null, teacher_id?: int|null, subject_id?: int|null, day_of_week?: int|null, status?: string|null}  $filters
     * @return Builder<ClassSchedule>
     */
    public function schedules(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $day = (int) ($filters['day_of_week'] ?? 0);

        return ClassSchedule::query()
            ->with([
                'academicYear:id,code,label',
                'term:id,label,sequence',
                'class:id,grade_level,name,academic_year_id',
                'subject:id,code,name',
                'teacher:id,legal_name,preferred_name',
            ])
            ->when((int) ($filters['academic_year_id'] ?? 0) > 0, fn (Builder $query): Builder => $query->where('academic_year_id', (int) $filters['academic_year_id']))
            ->when((int) ($filters['term_id'] ?? 0) > 0, fn (Builder $query): Builder => $query->where('term_id', (int) $filters['term_id']))
            ->when((int) ($filters['class_id'] ?? 0) > 0, fn (Builder $query): Builder => $query->where('class_id', (int) $filters['class_id']))
            ->when((int) ($filters['teacher_id'] ?? 0) > 0, fn (Builder $query): Builder => $query->where('teacher_id', (int) $filters['teacher_id']))
            ->when((int) ($filters['subject_id'] ?? 0) > 0, fn (Builder $query): Builder => $query->where('subject_id', (int) $filters['subject_id']))
            ->when($day > 0, fn (Builder $query): Builder => $query->where('day_of_week', $day))
            ->when($status !== '', fn (Builder $query): Builder => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function (Builder $query) use ($like): Builder {
                    $query->where('room', 'like', $like)
                        ->orWhereHas('subject', fn (Builder $query): Builder => $query->where('name', 'like', $like))
                        ->orWhereHas('teacher', fn (Builder $query): Builder => $query->where('legal_name', 'like', $like));
                });
            })
            ->ordered();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): ClassSchedule
    {
        Gate::forUser($actor)->authorize('create', ClassSchedule::class);
        $scope = $this->assertScopeIsCoherent($data);
        $lesson = $this->lessonShape($data, $scope);

        $schedule = DB::transaction(function () use ($actor, $scope, $lesson, $data): ClassSchedule {
            $this->serialiseTimetableWrites($scope);
            $this->assertNoConflict($lesson);

            $schedule = ClassSchedule::query()->create([
                'academic_year_id' => $scope['academic_year_id'],
                'term_id' => $scope['term_id'],
                'class_id' => $scope['class_id'],
                'subject_id' => $scope['subject_id'],
                'teacher_id' => $scope['teacher_id'],
                'day_of_week' => $lesson['day_of_week'],
                'starts_at' => $lesson['starts_at'],
                'ends_at' => $lesson['ends_at'],
                'room' => $lesson['room'],
                'status' => (string) ($data['status'] ?? ScheduleStatus::Active->value),
            ]);

            $this->audit->recordOrFail($actor, 'class_schedule.created', 'class_schedule', $schedule->getKey(), context: [
                'class_id' => $schedule->class_id,
                'teacher_id' => $schedule->teacher_id,
                'day_of_week' => $schedule->day_of_week->value,
                'status' => $schedule->status->value,
            ]);

            return $schedule;
        });

        $this->announce($schedule, 'Jadwal pelajaran baru ditambahkan', 'ditambahkan ke jadwal Anda.');

        return $schedule->refresh();
    }

    /**
     * Serialise concurrent timetable writes that touch the same class or teacher.
     *
     * MySQL cannot express "these ranges must not overlap" as a constraint, so the
     * three conflict rules are enforced by a read inside the write transaction.
     * That is only sound if a competing writer cannot slip between the read and
     * the insert, which means taking a row lock on the rows the check reads. Two
     * concurrent requests for the same class — or for the same teacher — then
     * queue on the same lock, and the second re-reads after the first commits and
     * sees the conflict.
     *
     * Lock order is fixed (class, then teacher) so two requests naming the same
     * class and teacher cannot deadlock by taking them in opposite orders.
     *
     * Honest limitation: the room dimension is not serialised the same way,
     * because a room is a free-text string on the lesson rather than a row that
     * can be locked — there is no rooms table to lock. Two administrators booking
     * the *same room* for *different classes* at the same minute can therefore
     * both succeed. Closing that needs a rooms table with a real row per room;
     * inventing one here would be a schema change this change set should not make
     * on the fly. The class and teacher dimensions, which are the ones that
     * actually block a timetable from working, are covered.
     *
     * @param  array{class_id: int, teacher_id: int}  $scope
     */
    private function serialiseTimetableWrites(array $scope): void
    {
        ClassModel::query()->whereKey($scope['class_id'])->lockForUpdate()->first();
        Teacher::query()->whereKey($scope['teacher_id'])->lockForUpdate()->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, ClassSchedule $schedule, array $data): ClassSchedule
    {
        Gate::forUser($actor)->authorize('update', $schedule);
        $scope = $this->assertScopeIsCoherent($data);
        $lesson = $this->lessonShape($data, $scope);

        DB::transaction(function () use ($actor, $schedule, $scope, $lesson, $data): void {
            $this->serialiseTimetableWrites($scope);
            $this->assertNoConflict($lesson, $schedule->getKey());

            $schedule->fill([
                'academic_year_id' => $scope['academic_year_id'],
                'term_id' => $scope['term_id'],
                'class_id' => $scope['class_id'],
                'subject_id' => $scope['subject_id'],
                'teacher_id' => $scope['teacher_id'],
                'day_of_week' => $lesson['day_of_week'],
                'starts_at' => $lesson['starts_at'],
                'ends_at' => $lesson['ends_at'],
                'room' => $lesson['room'],
                'status' => (string) ($data['status'] ?? $schedule->status->value),
            ])->save();

            $this->audit->recordOrFail($actor, 'class_schedule.updated', 'class_schedule', $schedule->getKey(), context: [
                'class_id' => $schedule->class_id,
                'teacher_id' => $schedule->teacher_id,
                'day_of_week' => $schedule->day_of_week->value,
                'status' => $schedule->status->value,
            ]);
        });

        $this->announce($schedule->refresh(), 'Jadwal pelajaran diperbarui', 'diperbarui di jadwal Anda.');

        return $schedule->refresh();
    }

    /**
     * A schedule is never deleted: attendance records reference it. Removing one
     * from the timetable is a deactivation, which is reversible and auditable.
     */
    public function deactivate(User $actor, ClassSchedule $schedule): ClassSchedule
    {
        Gate::forUser($actor)->authorize('deactivate', $schedule);

        if (! $schedule->isActive()) {
            return $schedule;
        }

        DB::transaction(function () use ($actor, $schedule): void {
            $schedule->forceFill(['status' => ScheduleStatus::Inactive])->save();

            $this->audit->recordOrFail($actor, 'class_schedule.deactivated', 'class_schedule', $schedule->getKey(), context: [
                'class_id' => $schedule->class_id,
                'teacher_id' => $schedule->teacher_id,
            ]);
        });

        $this->announce($schedule->refresh(), 'Jadwal pelajaran dinonaktifkan', 'dihapus dari jadwal Anda.');

        return $schedule->refresh();
    }

    public function reactivate(User $actor, ClassSchedule $schedule): ClassSchedule
    {
        Gate::forUser($actor)->authorize('update', $schedule);

        if ($schedule->isActive()) {
            return $schedule;
        }

        DB::transaction(function () use ($actor, $schedule): void {
            $this->serialiseTimetableWrites([
                'class_id' => (int) $schedule->class_id,
                'teacher_id' => (int) $schedule->teacher_id,
            ]);

            $this->assertNoConflict([
                'class_id' => (int) $schedule->class_id,
                'teacher_id' => (int) $schedule->teacher_id,
                'day_of_week' => $schedule->day_of_week->value,
                'starts_at' => substr((string) $schedule->starts_at, 0, 5),
                'ends_at' => substr((string) $schedule->ends_at, 0, 5),
                'room' => $schedule->room,
            ], $schedule->getKey());

            $schedule->forceFill(['status' => ScheduleStatus::Active])->save();

            $this->audit->recordOrFail($actor, 'class_schedule.reactivated', 'class_schedule', $schedule->getKey(), context: [
                'class_id' => $schedule->class_id,
            ]);
        });

        return $schedule->refresh();
    }

    /**
     * Build the weekly grid for a scope.
     *
     * Accepts either a query or an already-loaded collection, so the read paths
     * that already have a scoped collection (a teacher's week, a student's class)
     * do not re-query just to group it.
     *
     * @param  Builder<ClassSchedule>|Collection<int, ClassSchedule>  $source
     * @return array<int, array{day: WeekDay, lessons: Collection<int, ClassSchedule>}>
     */
    public function weeklyGrid(Builder|Collection $source, array $days = []): array
    {
        $lessons = $source instanceof Builder ? $source->get() : $source;
        $grid = [];

        foreach ($days === [] ? WeekDay::mondayFirst() : $days as $day) {
            $grid[] = [
                'day' => $day,
                'lessons' => $lessons->where('day_of_week', $day)->values(),
            ];
        }

        return $grid;
    }

    /**
     * The scope of a write, resolved and proven coherent.
     *
     * @param  array<string, mixed>  $data
     * @return array{academic_year_id: int, term_id: int, class_id: int, subject_id: int, teacher_id: int}
     */
    private function assertScopeIsCoherent(array $data): array
    {
        $class = ClassModel::query()->find((int) $data['class_id']);
        $term = Term::query()->find((int) $data['term_id']);
        $subject = Subject::query()->find((int) $data['subject_id']);
        $teacher = Teacher::query()->find((int) $data['teacher_id']);

        if (! $class instanceof ClassModel) {
            throw ValidationException::withMessages(['class_id' => 'Kelas yang dipilih tidak ditemukan.']);
        }

        if (! $term instanceof Term) {
            throw ValidationException::withMessages(['term_id' => 'Semester yang dipilih tidak ditemukan.']);
        }

        if (! $subject instanceof Subject) {
            throw ValidationException::withMessages(['subject_id' => 'Mata pelajaran yang dipilih tidak ditemukan.']);
        }

        if (! $teacher instanceof Teacher) {
            throw ValidationException::withMessages(['teacher_id' => 'Guru yang dipilih tidak ditemukan.']);
        }

        // A schedule row must not be able to straddle two academic years: the
        // class, the semester and therefore the lesson all share one year.
        if ((int) $term->academic_year_id !== (int) $class->academic_year_id) {
            throw ValidationException::withMessages([
                'term_id' => 'Semester harus milik tahun ajaran yang sama dengan kelas.',
            ]);
        }

        $academicYearId = (int) $class->academic_year_id;
        $academicYear = AcademicYear::query()->find($academicYearId);
        if (! $academicYear instanceof AcademicYear) {
            throw ValidationException::withMessages(['class_id' => 'Tahun ajaran kelas tidak ditemukan.']);
        }

        if ((int) $data['day_of_week'] < 1 || (int) $data['day_of_week'] > 7) {
            throw ValidationException::withMessages(['day_of_week' => 'Hari tidak valid.']);
        }

        $day = WeekDay::from((int) $data['day_of_week']);
        if (! in_array($day, WeekDay::allowed(), true)) {
            throw ValidationException::withMessages([
                'day_of_week' => 'Hari '.$day->label().' tidak termasuk hari mengajar sekolah ini.',
            ]);
        }

        // The teacher has to be connected to this class and subject in this
        // semester, otherwise the timetable would promise a lesson nobody is
        // assigned to teach and every downstream scope check would disagree.
        $assigned = $this->teacherIsAssigned($teacher, $class, $subject, $academicYear, $term);

        if (! $assigned['teaching'] && ! $assigned['homeroom']) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Guru ini belum memiliki assignment mengajar untuk kelas, mata pelajaran, dan semester tersebut.',
            ]);
        }

        return [
            'academic_year_id' => $academicYearId,
            'term_id' => (int) $term->getKey(),
            'class_id' => (int) $class->getKey(),
            'subject_id' => (int) $subject->getKey(),
            'teacher_id' => (int) $teacher->getKey(),
        ];
    }

    /**
     * @return array{teaching: bool, homeroom: bool}
     */
    private function teacherIsAssigned(
        Teacher $teacher,
        ClassModel $class,
        Subject $subject,
        AcademicYear $academicYear,
        Term $term,
    ): array {
        $teaching = $teacher->teachingAssignments()
            ->where('class_id', $class->getKey())
            ->where('subject_id', $subject->getKey())
            ->where('academic_year_id', $academicYear->getKey())
            ->where('term_id', $term->getKey())
            ->where('status', AssignmentStatus::Active->value)
            ->exists();

        $homeroom = $teacher->classTeacherAssignments()
            ->where('class_id', $class->getKey())
            ->where('status', AssignmentStatus::Active->value)
            ->exists();

        return ['teaching' => $teaching, 'homeroom' => $homeroom];
    }

    /**
     * Reject an overlapping lesson for the class, the teacher, and the room.
     *
     * @param  array{class_id: int, teacher_id: int, day_of_week: int, starts_at: string, ends_at: string, room: string|null}  $lesson
     */
    private function assertNoConflict(array $lesson, ?int $ignoreId = null): void
    {
        $startsAt = $lesson['starts_at'];
        $endsAt = $lesson['ends_at'];

        if ($startsAt >= $endsAt) {
            throw ValidationException::withMessages([
                'ends_at' => 'Waktu selesai harus setelah waktu mulai.',
            ]);
        }

        /*
         * Half-open interval comparison: a lesson ending exactly when the next one
         * starts is a legal back-to-back timetable, not a clash.
         */
        $overlapping = function (Builder $query) use ($lesson, $startsAt, $endsAt, $ignoreId): Builder {
            return $query
                ->where('day_of_week', $lesson['day_of_week'])
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->where('status', ScheduleStatus::Active->value)
                ->when($ignoreId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId));
        };

        if (ClassSchedule::query()->where('class_id', $lesson['class_id'])->where($overlapping)->exists()) {
            throw ValidationException::withMessages([
                'starts_at' => 'Kelas ini sudah memiliki pelajaran lain pada rentang waktu tersebut.',
            ]);
        }

        if (ClassSchedule::query()->where('teacher_id', $lesson['teacher_id'])->where($overlapping)->exists()) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Guru ini sudah mengajar kelas lain pada rentang waktu tersebut.',
            ]);
        }

        if ($lesson['room'] !== null && ClassSchedule::query()->where('room', $lesson['room'])->where($overlapping)->exists()) {
            throw ValidationException::withMessages([
                'room' => 'Ruang ini sudah dipakai pelajaran lain pada rentang waktu tersebut.',
            ]);
        }
    }

    /**
     * Normalise the submitted lesson into the shape the conflict check needs.
     *
     * @param  array<string, mixed>  $data
     * @param  array{academic_year_id: int, term_id: int, class_id: int, subject_id: int, teacher_id: int}  $scope
     * @return array{class_id: int, teacher_id: int, day_of_week: int, starts_at: string, ends_at: string, room: string|null}
     */
    private function lessonShape(array $data, array $scope): array
    {
        return [
            'class_id' => $scope['class_id'],
            'teacher_id' => $scope['teacher_id'],
            'day_of_week' => (int) $data['day_of_week'],
            'starts_at' => $this->normaliseTime((string) $data['starts_at']),
            'ends_at' => $this->normaliseTime((string) $data['ends_at']),
            'room' => $this->blankToNull($data['room'] ?? null),
        ];
    }

    private function announce(ClassSchedule $schedule, string $title, string $suffix): void
    {
        $teacher = $schedule->teacher;

        if (! $teacher instanceof Teacher || $teacher->user === null) {
            return;
        }

        $day = $schedule->day_of_week->label();
        $lines = [
            $schedule->class?->getFullName().' · '.$schedule->subject?->name,
            $day.' '.$schedule->timeRange().($schedule->room !== null ? ' · Ruang '.$schedule->room : ''),
            'Jadwal ini '.$suffix,
        ];

        $this->notifications->deliver(
            $teacher->user,
            new ScheduleChangedNotification($title, $lines, route('guru.jadwal.index')),
            NotificationCategory::Academic,
            $teacher->phone,
        );
    }

    /**
     * The lessons a student should see, scoped to their own class only.
     *
     * @return Collection<int, ClassSchedule>
     */
    public function studentWeek(ClassModel $class, int $academicYearId, ?int $termId = null): Collection
    {
        return ClassSchedule::query()
            ->with(['subject:id,code,name', 'teacher:id,legal_name,preferred_name'])
            ->where('class_id', $class->getKey())
            ->where('academic_year_id', $academicYearId)
            ->when($termId !== null, fn (Builder $query): Builder => $query->where('term_id', $termId))
            ->where('status', ScheduleStatus::Active->value)
            ->ordered()
            ->get();
    }

    /**
     * The lessons a teacher is personally timetabled for.
     *
     * @return Collection<int, ClassSchedule>
     */
    public function teacherWeek(int $teacherId, int $academicYearId, ?int $termId = null): Collection
    {
        return ClassSchedule::query()
            ->with(['subject:id,code,name', 'class:id,grade_level,name,academic_year_id'])
            ->where('teacher_id', $teacherId)
            ->where('academic_year_id', $academicYearId)
            ->when($termId !== null, fn (Builder $query): Builder => $query->where('term_id', $termId))
            ->where('status', ScheduleStatus::Active->value)
            ->ordered()
            ->get();
    }

    /**
     * The next lesson after a given moment, for the "next class" hint.
     *
     * @param  Collection<int, ClassSchedule>  $lessons
     * @return array{lesson: ClassSchedule, starts_in: int}|null
     */
    public function nextLesson(Collection $lessons, int $dayOfWeek, string $now = '00:00:00'): ?array
    {
        $todays = $lessons
            ->filter(fn (ClassSchedule $lesson): bool => (int) $lesson->day_of_week->value === $dayOfWeek)
            ->filter(fn (ClassSchedule $lesson): bool => (string) $lesson->starts_at >= $now)
            ->sortBy('starts_at')
            ->values();

        $next = $todays->first();

        if ($next === null) {
            return null;
        }

        $startsIn = (int) round((strtotime((string) $next->starts_at) - strtotime($now)) / 60);

        return ['lesson' => $next, 'starts_in' => max(0, $startsIn)];
    }

    private function normaliseTime(string $time): string
    {
        $time = trim($time);

        if (preg_match('/^\d{1,2}:\d{2}$/', $time) === 1) {
            return strlen($time) === 4 ? '0'.$time : $time;
        }

        return strlen($time) >= 5 ? substr($time, 0, 5) : $time;
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : Str::limit($string, 50, '');
    }
}
