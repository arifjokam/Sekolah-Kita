<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Enums\AttendanceSessionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\ScheduleStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\ClassModel;
use App\Models\ClassSchedule;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Digital attendance.
 *
 * The unit of work is a session: one scheduled lesson on one date. A session is
 * opened from a schedule the teacher is actually timetabled for, is filled from
 * the class's current enrollment, and can only be corrected through the explicit
 * correction path, which is audited separately.
 *
 * Authorisation is not re-derived here: every method authorises through
 * AttendanceSessionPolicy first, so a teacher who is not on the lesson's
 * timetable cannot reach any of it, including the monthly report.
 */
class AttendanceService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * The lessons a teacher can open attendance for, newest scope first.
     *
     * @return Builder<AttendanceSession>
     */
    public function sessionsForTeacher(Teacher $teacher, ?int $termId = null, ?string $date = null): Builder
    {
        $classIds = $this->teacherClassIds($teacher);

        return AttendanceSession::query()
            ->with(['class:id,grade_level,name,academic_year_id', 'subject:id,code,name', 'term:id,label,sequence'])
            ->where('teacher_id', $teacher->getKey())
            ->when($classIds !== [], fn (Builder $query): Builder => $query->orWhereIn('class_id', $classIds))
            ->when($termId !== null, fn (Builder $query): Builder => $query->where('term_id', $termId))
            ->when($date !== null, fn (Builder $query): Builder => $query->onDate($date))
            ->orderByDesc('session_date')
            ->orderBy('starts_at');
    }

    /**
     * The class ids a teacher is accountable for: the ones they teach plus the
     * ones they are the homeroom teacher of.
     *
     * @return list<int>
     */
    public function teacherClassIds(Teacher $teacher): array
    {
        return $teacher->teachingAssignments()
            ->where('status', AssignmentStatus::Active->value)
            ->distinct()
            ->pluck('class_id')
            ->merge(
                $teacher->classTeacherAssignments()
                    ->where('status', AssignmentStatus::Active->value)
                    ->distinct()
                    ->pluck('class_id')
            )
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Today's scheduled lessons a teacher may take attendance for.
     *
     * @return Collection<int, ClassSchedule>
     */
    public function todaysLessons(Teacher $teacher, ?int $termId = null, ?Carbon $date = null): Collection
    {
        $date ??= today();
        $classIds = $this->teacherClassIds($teacher);

        if ($classIds === []) {
            return new Collection;
        }

        return ClassSchedule::query()
            ->with(['subject:id,code,name', 'class:id,grade_level,name,academic_year_id', 'term:id,label,sequence'])
            ->where('teacher_id', $teacher->getKey())
            ->where('status', ScheduleStatus::Active->value)
            ->whereIn('class_id', $classIds)
            ->when($termId !== null, fn (Builder $query): Builder => $query->where('term_id', $termId))
            ->onDate($date)
            ->ordered()
            ->get();
    }

    /**
     * Open (or return the existing) session for a scheduled lesson on a date.
     *
     * The unique (schedule_id, session_date) index is the real guarantee; this
     * method additionally takes a row lock so two concurrent "Mulai absensi"
     * clicks cannot both decide the session does not exist yet.
     */
    public function openSession(User $actor, ClassSchedule $schedule, Carbon $date): AttendanceSession
    {
        Gate::forUser($actor)->authorize('view', $schedule);
        Gate::forUser($actor)->authorize('create', AttendanceSession::class);

        $teacher = $actor->teacher;
        if (! $teacher instanceof Teacher) {
            throw ValidationException::withMessages(['schedule' => 'Akun ini bukan akun guru.']);
        }

        $dateString = $date->toDateString();

        if (! $schedule->occursOn($date)) {
            throw ValidationException::withMessages([
                'date' => 'Pelajaran ini tidak jatuh pada hari '.$date->translatedFormat('l, d M Y').'.',
            ]);
        }

        return DB::transaction(function () use ($actor, $schedule, $dateString): AttendanceSession {
            $existing = AttendanceSession::query()
                ->where('schedule_id', $schedule->getKey())
                ->where('session_date', $dateString)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof AttendanceSession) {
                return $existing;
            }

            /*
             * teacher_id is a snapshot of the *timetable*, not of whoever pressed
             * the button. A homeroom teacher is allowed to open a register for a
             * subject they do not teach, and writing the actor here would make the
             * lesson disappear from the actual subject teacher's list forever
             * (todaysLessons filters on it) while claiming the wrong person taught
             * it. The actor is recorded separately, on the session and on every
             * record, which is where "who filled this in" belongs.
             */
            $session = AttendanceSession::query()->create([
                'schedule_id' => $schedule->getKey(),
                'academic_year_id' => $schedule->academic_year_id,
                'term_id' => $schedule->term_id,
                'class_id' => $schedule->class_id,
                'subject_id' => $schedule->subject_id,
                'teacher_id' => $schedule->teacher_id,
                'session_date' => $dateString,
                'starts_at' => substr((string) $schedule->starts_at, 0, 5),
                'ends_at' => substr((string) $schedule->ends_at, 0, 5),
                'status' => AttendanceSessionStatus::Open,
            ]);

            $this->audit->recordOrFail($actor, 'attendance_session.opened', 'attendance_session', $session->getKey(), context: [
                'class_id' => $session->class_id,
                'schedule_id' => $session->schedule_id,
                'session_date' => $dateString,
            ]);

            return $session;
        }, attempts: 3);
    }

    /**
     * The roster a session is filled from: the class's current enrollment.
     *
     * @return Collection<int, Student>
     */
    public function roster(AttendanceSession $session): Collection
    {
        $enrollments = Enrollment::query()
            ->with('student:id,legal_name,preferred_name,student_number,gender')
            ->where('class_id', $session->class_id)
            ->where('status', EnrollmentStatus::Active->value)
            ->orderBy('student_id')
            ->get();

        return $enrollments
            ->pluck('student')
            ->filter(static fn (?Student $student): bool => $student !== null)
            ->sortBy('legal_name')
            ->values();
    }

    /**
     * The marks already saved for a session, keyed by student id.
     *
     * @return Collection<int, AttendanceRecord>
     */
    public function existingRecords(AttendanceSession $session): Collection
    {
        return $session->records()->get()->keyBy('student_id');
    }

    /**
     * Save the sheet.
     *
     * @param  array<int|string, array{status?: string, note?: string|null}>  $marks
     */
    public function save(
        User $actor,
        AttendanceSession $session,
        array $marks,
        bool $correction = false,
    ): AttendanceSession {
        Gate::forUser($actor)->authorize($correction ? 'correct' : 'submit', $session);

        $roster = $this->roster($session);
        $rosterIds = $roster->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        $normalised = $this->normaliseMarks($marks, $rosterIds);

        if ($normalised === []) {
            throw ValidationException::withMessages([
                'records' => 'Tidak ada absensi siswa yang tersimpan untuk sesi ini.',
            ]);
        }

        DB::transaction(function () use ($actor, $session, $normalised, $correction): void {
            /*
             * Lock the session and re-read it inside the transaction. `submit` and
             * `correct` are state-dependent abilities, and the revision counter
             * below is read from a record — so a second, concurrently authorised
             * save (a subject teacher and the homeroom teacher both may mark)
             * would otherwise compute the same revision, or a second save would
             * land on a sheet that had just been closed and trip the
             * (session, student) unique index as a 500.
             */
            $locked = AttendanceSession::query()
                ->whereKey($session->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($actor)->authorize(
                $correction ? 'correct' : 'submit',
                $locked,
            );

            $existing = AttendanceRecord::query()
                ->where('attendance_session_id', $locked->getKey())
                ->get()
                ->keyBy('student_id');
            $now = now();

            foreach ($normalised as $studentId => $mark) {
                $record = $existing->get($studentId);

                if ($record instanceof AttendanceRecord) {
                    $record->forceFill([
                        'status' => $mark['status'],
                        'note' => $mark['note'],
                        'marked_by_user_id' => $actor->getKey(),
                        'marked_at' => $now,
                        'revision' => ((int) $record->revision) + 1,
                    ])->save();

                    continue;
                }

                AttendanceRecord::query()->create([
                    'attendance_session_id' => $locked->getKey(),
                    'student_id' => $studentId,
                    'status' => $mark['status'],
                    'note' => $mark['note'],
                    'marked_by_user_id' => $actor->getKey(),
                    'marked_at' => $now,
                    'revision' => 0,
                ]);
            }

            $locked->forceFill([
                'status' => $correction
                    ? AttendanceSessionStatus::Corrected
                    : AttendanceSessionStatus::Submitted,
                'submitted_at' => $locked->submitted_at ?? $now,
                'marked_by_user_id' => $actor->getKey(),
            ])->save();

            $this->audit->recordOrFail(
                $actor,
                $correction ? 'attendance_session.corrected' : 'attendance_session.submitted',
                'attendance_session',
                $locked->getKey(),
                context: [
                    'class_id' => $locked->class_id,
                    'session_date' => $locked->session_date->toDateString(),
                    'record_count' => count($normalised),
                    'correction' => $correction,
                ],
            );
        }, attempts: 3);

        return $session->refresh();
    }

    /**
     * The summary a teacher reads per class for a month.
     *
     * @return array{students: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function monthlyClassReport(
        Teacher $teacher,
        ClassModel $class,
        Carbon $month,
        ?int $subjectId = null,
    ): array {
        Gate::forUser($teacher->user, 'view', $class);

        if (! in_array((int) $class->getKey(), $this->teacherClassIds($teacher), true)) {
            throw ValidationException::withMessages([
                'class_id' => 'Kelas ini berada di luar lingkup mengajar Anda.',
            ]);
        }

        $enrollments = Enrollment::query()
            ->where('class_id', $class->getKey())
            ->where('status', EnrollmentStatus::Active->value)
            ->get(['student_id']);

        $studentIds = $enrollments->pluck('student_id')->map(static fn ($id): int => (int) $id)->all();

        if ($studentIds === []) {
            return ['students' => [], 'totals' => $this->emptyTotals(), 'session_count' => 0];
        }

        $sessionIds = AttendanceSession::query()
            ->where('class_id', $class->getKey())
            ->inMonth((int) $month->year, (int) $month->month)
            ->when($subjectId !== null, fn (Builder $query): Builder => $query->where('subject_id', $subjectId))
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $sessionCount = count($sessionIds);

        // Always a Collection, including when the month has no sessions at all —
        // an empty array here would make the per-student lookup below fail on a
        // month where nobody has been marked.
        $rows = collect();

        if ($sessionIds !== []) {
            $rows = AttendanceRecord::query()
                ->whereIn('attendance_session_id', $sessionIds)
                ->whereIn('student_id', $studentIds)
                ->get(['student_id', 'status'])
                ->groupBy('student_id')
                ->map(function (Collection $records) use ($sessionCount): array {
                    $counts = $this->emptyTotals();

                    foreach ($records as $record) {
                        $counts[$record->status->value] = ($counts[$record->status->value] ?? 0) + 1;
                    }

                    $counts['total'] = $records->count();
                    $counts['percentage'] = $this->percentage(
                        ($counts[AttendanceStatus::Present->value] ?? 0) + ($counts[AttendanceStatus::Late->value] ?? 0),
                        $sessionCount,
                    );

                    return $counts;
                });
        }

        $students = Student::query()
            ->whereIn('id', $studentIds)
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'preferred_name', 'student_number'])
            ->map(function (Student $student) use ($rows, $sessionCount): array {
                // The grouping above already produced a plain counts array per
                // student. A student with no record this month simply has none and
                // starts from the zeroed shape, rather than from missing keys.
                $counts = $rows->get($student->getKey()) ?? $this->emptyTotals();
                $counts['percentage'] = $this->percentage(
                    ($counts[AttendanceStatus::Present->value] ?? 0) + ($counts[AttendanceStatus::Late->value] ?? 0),
                    $sessionCount,
                );
                $counts['total'] = $counts['total'] ?? 0;
                $counts['session_count'] = $sessionCount;

                return [
                    'student' => $student,
                    'counts' => $counts,
                ];
            })
            ->values()
            ->all();

        $totals = $this->emptyTotals();
        foreach ($students as $row) {
            foreach ($totals as $key => $_) {
                $totals[$key] = ($totals[$key] ?? 0) + (int) ($row['counts'][$key] ?? 0);
            }
        }

        return ['students' => $students, 'totals' => $totals, 'session_count' => $sessionCount];
    }

    /**
     * A single student's own attendance summary, for their dashboard.
     *
     * @return array{totals: array<string, int>, percentage: float, session_count: int, recent: Collection<int, array<string, mixed>>}
     */
    public function studentSummary(Student $student, ?int $termId = null, ?int $limit = 20): array
    {
        /*
         * The totals are aggregated in the database and the recent list is
         * bounded, so neither grows with how long the student has been at the
         * school. Doing this in PHP instead meant reading every record the student
         * has ever had, hydrated with its session, on every visit to a screen that
         * shows twenty lines.
         *
         * The only raw fragment here is `count(*)`, a literal with nothing
         * interpolated into it; the column names and the group key are model
         * columns, never request input.
         */
        $totals = $this->emptyTotals();

        $counted = AttendanceRecord::query()
            ->where('student_id', $student->getKey())
            ->when($termId !== null, fn (Builder $query): Builder => $query->whereHas(
                'session',
                fn (Builder $query): Builder => $query->where('term_id', $termId),
            ))
            ->select('status')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('status')
            ->get();

        foreach ($counted as $row) {
            $totals[$row->status->value] = (int) $row->getAttribute('aggregate');
        }

        $totals['total'] = (int) $counted->sum(fn (AttendanceRecord $row): int => (int) $row->getAttribute('aggregate'));

        $recent = AttendanceRecord::query()
            ->with('session:id,class_id,subject_id,session_date,status')
            ->where('student_id', $student->getKey())
            ->when($termId !== null, fn (Builder $query): Builder => $query->whereHas(
                'session',
                fn (Builder $query): Builder => $query->where('term_id', $termId),
            ))
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get();

        $attended = ($totals[AttendanceStatus::Present->value] ?? 0) + ($totals[AttendanceStatus::Late->value] ?? 0);

        return [
            'totals' => $totals,
            'percentage' => $this->percentage($attended, $totals['total']),
            'session_count' => $totals['total'],
            'recent' => $recent->map(fn (AttendanceRecord $record): array => [
                'record' => $record,
                'date' => $record->session?->session_date,
            ])->values(),
        ];
    }

    /**
     * Normalise submitted marks, rejecting anything outside the roster.
     *
     * @param  array<int|string, mixed>  $marks
     * @param  list<int>  $rosterIds
     * @return array<int, array{status: AttendanceStatus, note: string|null}>
     */
    private function normaliseMarks(array $marks, array $rosterIds): array
    {
        $normalised = [];

        foreach ($marks as $studentId => $mark) {
            $studentId = (int) $studentId;

            if (! in_array($studentId, $rosterIds, true)) {
                // A submitted student who is not on the roster is either a
                // tampered form or a stale page. Refuse the whole save rather
                // than silently dropping the row.
                throw ValidationException::withMessages([
                    'records' => 'Absensi memuat siswa yang tidak terdaftar di kelas ini.',
                ]);
            }

            if (! is_array($mark)) {
                continue;
            }

            $status = AttendanceStatus::tryFrom((string) ($mark['status'] ?? ''));

            if ($status === null) {
                throw ValidationException::withMessages([
                    "records.{$studentId}.status" => 'Status kehadiran tidak valid.',
                ]);
            }

            $note = trim((string) ($mark['note'] ?? ''));
            $note = $note === '' ? null : Str::limit($note, 255, '');

            if ($status->requiresNote() && $note === null) {
                throw ValidationException::withMessages([
                    "records.{$studentId}.note" => 'Catatan wajib diisi untuk status '.$status->label().'.',
                ]);
            }

            $normalised[$studentId] = ['status' => $status, 'note' => $note];
        }

        return $normalised;
    }

    /**
     * @return array<string, int>
     */
    private function emptyTotals(): array
    {
        $totals = [];

        foreach (AttendanceStatus::cases() as $status) {
            $totals[$status->value] = 0;
        }

        return $totals + ['total' => 0];
    }

    /**
     * Attendance percentage, guarding against a zero denominator.
     */
    private function percentage(int $attended, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($attended / $total) * 100, 1);
    }

    /**
     * The academic scope a session should inherit, used by the admin tooling.
     */
    public function scopeForClass(ClassModel $class): array
    {
        return [
            'academic_year_id' => (int) $class->academic_year_id,
            'class' => $class,
            'academicYear' => AcademicYear::query()->find($class->academic_year_id),
        ];
    }
}
