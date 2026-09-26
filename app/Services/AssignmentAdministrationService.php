<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Models\ClassModel;
use App\Models\ClassTeacherAssignment;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Administrator write path for teaching and class teacher assignments.
 *
 * Both assignment types share the same temporal rules, so they are written here
 * rather than duplicated in two services: a teacher holds at most one active
 * class teacher assignment per class, and at most one active teaching
 * assignment per class and subject.
 */
class AssignmentAdministrationService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array{search?: string|null, status?: string|null, teacher_id?: int|null, class_id?: int|null, subject_id?: int|null, academic_year_id?: int|null}  $filters
     * @return Builder<TeachingAssignment>
     */
    public function teachingAssignments(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return TeachingAssignment::query()
            ->with([
                'teacher:id,legal_name,preferred_name',
                'class:id,grade_level,name,academic_year_id',
                'subject:id,code,name',
                'academicYear:id,code,label',
                'term:id,label,sequence',
            ])
            ->when((int) ($filters['teacher_id'] ?? 0) > 0, fn (Builder $query): Builder => $query->where('teacher_id', (int) $filters['teacher_id']))
            ->when((int) ($filters['class_id'] ?? 0) > 0, fn (Builder $query): Builder => $query->where('class_id', (int) $filters['class_id']))
            ->when((int) ($filters['subject_id'] ?? 0) > 0, fn (Builder $query): Builder => $query->where('subject_id', (int) $filters['subject_id']))
            ->when((int) ($filters['academic_year_id'] ?? 0) > 0, fn (Builder $query): Builder => $query->where('academic_year_id', (int) $filters['academic_year_id']))
            ->when(($filters['status'] ?? '') !== '', fn (Builder $query): Builder => $query->where('status', (string) $filters['status']))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->whereHas('teacher', fn (Builder $query): Builder => $query->where('legal_name', 'like', $like))
                        ->orWhereHas('subject', fn (Builder $query): Builder => $query
                            ->where(fn (Builder $query): Builder => $query
                                ->where('code', 'like', $like)
                                ->orWhere('name', 'like', $like)));
                });
            })
            ->orderByDesc('academic_year_id')
            ->orderBy('class_id')
            ->orderBy('subject_id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTeachingAssignment(User $actor, array $data): TeachingAssignment
    {
        Gate::forUser($actor)->authorize('create', TeachingAssignment::class);

        $class = ClassModel::query()->findOrFail((int) $data['class_id']);
        $this->assertClassOwnsYear($class, (int) $data['academic_year_id']);
        $this->assertTermBelongsToYear($data, (int) $data['academic_year_id']);
        $this->assertTeachingWindowIsFree(
            (int) $data['teacher_id'],
            (int) $data['class_id'],
            (int) $data['subject_id'],
        );

        $assignment = DB::transaction(function () use ($actor, $data): TeachingAssignment {
            $assignment = TeachingAssignment::query()->create([
                'teacher_id' => (int) $data['teacher_id'],
                'class_id' => (int) $data['class_id'],
                'subject_id' => (int) $data['subject_id'],
                'academic_year_id' => (int) $data['academic_year_id'],
                'term_id' => $this->nullableId($data['term_id'] ?? null),
                'status' => AssignmentStatus::from((string) $data['status']),
                'starts_on' => (string) $data['starts_on'],
                'ends_on' => $this->blankToNull($data['ends_on'] ?? null),
                'internal_reason' => $this->blankToNull($data['internal_reason'] ?? null),
            ]);

            $this->audit->recordOrFail($actor, 'teaching_assignment.created', 'teaching_assignment', $assignment->getKey(), context: [
                'status' => $assignment->status->value,
            ]);

            return $assignment;
        });

        return $assignment->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTeachingAssignment(User $actor, TeachingAssignment $assignment, array $data): TeachingAssignment
    {
        Gate::forUser($actor)->authorize('update', $assignment);

        $class = ClassModel::query()->findOrFail((int) $data['class_id']);
        $this->assertClassOwnsYear($class, (int) $data['academic_year_id']);
        $this->assertTermBelongsToYear($data, (int) $data['academic_year_id']);

        if ((int) $assignment->teacher_id !== (int) $data['teacher_id']
            || (int) $assignment->class_id !== (int) $data['class_id']
            || (int) $assignment->subject_id !== (int) $data['subject_id']) {
            $this->assertTeachingWindowIsFree(
                (int) $data['teacher_id'],
                (int) $data['class_id'],
                (int) $data['subject_id'],
                (int) $assignment->getKey(),
            );
        }

        return DB::transaction(function () use ($actor, $assignment, $data): TeachingAssignment {
            $assignment->fill([
                'teacher_id' => (int) $data['teacher_id'],
                'class_id' => (int) $data['class_id'],
                'subject_id' => (int) $data['subject_id'],
                'academic_year_id' => (int) $data['academic_year_id'],
                'term_id' => $this->nullableId($data['term_id'] ?? null),
                'status' => AssignmentStatus::from((string) $data['status']),
                'starts_on' => (string) $data['starts_on'],
                'ends_on' => $this->blankToNull($data['ends_on'] ?? null),
                'internal_reason' => $this->blankToNull($data['internal_reason'] ?? null),
            ])->save();

            $this->audit->recordOrFail($actor, 'teaching_assignment.updated', 'teaching_assignment', $assignment->getKey(), context: [
                'status' => $assignment->status->value,
            ]);

            return $assignment;
        })->refresh();
    }

    public function changeTeachingAssignmentStatus(User $actor, TeachingAssignment $assignment, AssignmentStatus $status): TeachingAssignment
    {
        Gate::forUser($actor)->authorize('update', $assignment);

        if ($assignment->status === $status) {
            return $assignment;
        }

        if ($status === AssignmentStatus::Ended && $assignment->ends_on === null) {
            $assignment->forceFill(['ends_on' => today()->toDateString()])->save();
        }

        return DB::transaction(function () use ($actor, $assignment, $status): TeachingAssignment {
            $assignment->forceFill(['status' => $status])->save();

            $this->audit->recordOrFail($actor, 'teaching_assignment.status_changed', 'teaching_assignment', $assignment->getKey(), context: [
                'status' => $status->value,
            ]);

            return $assignment;
        })->refresh();
    }

    /**
     * @param  array{search?: string|null, status?: string|null, teacher_id?: int|null, class_id?: int|null}  $filters
     * @return Builder<ClassTeacherAssignment>
     */
    public function classTeacherAssignments(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return ClassTeacherAssignment::query()
            ->with([
                'teacher:id,legal_name,preferred_name',
                'class:id,grade_level,name,academic_year_id',
            ])
            ->when((int) ($filters['teacher_id'] ?? 0) > 0, fn (Builder $query): Builder => $query->where('teacher_id', (int) $filters['teacher_id']))
            ->when((int) ($filters['class_id'] ?? 0) > 0, fn (Builder $query): Builder => $query->where('class_id', (int) $filters['class_id']))
            ->when(($filters['status'] ?? '') !== '', fn (Builder $query): Builder => $query->where('status', (string) $filters['status']))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->whereHas('teacher', fn (Builder $query): Builder => $query->where('legal_name', 'like', $like));
            })
            ->orderByDesc('class_id')
            ->orderBy('teacher_id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createClassTeacherAssignment(User $actor, array $data): ClassTeacherAssignment
    {
        Gate::forUser($actor)->authorize('create', ClassTeacherAssignment::class);
        $this->assertHomeroomWindowIsFree((int) $data['class_id'], (int) $data['teacher_id']);

        $assignment = DB::transaction(function () use ($actor, $data): ClassTeacherAssignment {
            $assignment = ClassTeacherAssignment::query()->create([
                'class_id' => (int) $data['class_id'],
                'teacher_id' => (int) $data['teacher_id'],
                'status' => AssignmentStatus::from((string) $data['status']),
                'starts_on' => (string) $data['starts_on'],
                'ends_on' => $this->blankToNull($data['ends_on'] ?? null),
                'internal_reason' => $this->blankToNull($data['internal_reason'] ?? null),
            ]);

            $this->audit->recordOrFail($actor, 'class_teacher_assignment.created', 'class_teacher_assignment', $assignment->getKey(), context: [
                'status' => $assignment->status->value,
            ]);

            return $assignment;
        });

        return $assignment->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateClassTeacherAssignment(User $actor, ClassTeacherAssignment $assignment, array $data): ClassTeacherAssignment
    {
        Gate::forUser($actor)->authorize('update', $assignment);

        if ((int) $assignment->class_id !== (int) $data['class_id']
            || (int) $assignment->teacher_id !== (int) $data['teacher_id']) {
            $this->assertHomeroomWindowIsFree(
                (int) $data['class_id'],
                (int) $data['teacher_id'],
                (int) $assignment->getKey(),
            );
        }

        return DB::transaction(function () use ($actor, $assignment, $data): ClassTeacherAssignment {
            $assignment->fill([
                'class_id' => (int) $data['class_id'],
                'teacher_id' => (int) $data['teacher_id'],
                'status' => AssignmentStatus::from((string) $data['status']),
                'starts_on' => (string) $data['starts_on'],
                'ends_on' => $this->blankToNull($data['ends_on'] ?? null),
                'internal_reason' => $this->blankToNull($data['internal_reason'] ?? null),
            ])->save();

            $this->audit->recordOrFail($actor, 'class_teacher_assignment.updated', 'class_teacher_assignment', $assignment->getKey(), context: [
                'status' => $assignment->status->value,
            ]);

            return $assignment;
        })->refresh();
    }

    public function changeClassTeacherAssignmentStatus(User $actor, ClassTeacherAssignment $assignment, AssignmentStatus $status): ClassTeacherAssignment
    {
        Gate::forUser($actor)->authorize('update', $assignment);

        if ($assignment->status === $status) {
            return $assignment;
        }

        if ($status === AssignmentStatus::Ended && $assignment->ends_on === null) {
            $assignment->forceFill(['ends_on' => today()->toDateString()])->save();
        }

        return DB::transaction(function () use ($actor, $assignment, $status): ClassTeacherAssignment {
            $assignment->forceFill(['status' => $status])->save();

            $this->audit->recordOrFail($actor, 'class_teacher_assignment.status_changed', 'class_teacher_assignment', $assignment->getKey(), context: [
                'status' => $status->value,
            ]);

            return $assignment;
        })->refresh();
    }

    private function assertClassOwnsYear(ClassModel $class, int $academicYearId): void
    {
        if ((int) $class->academic_year_id !== $academicYearId) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Tahun ajaran harus sama dengan tahun ajaran kelas yang dipilih.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertTermBelongsToYear(array $data, int $academicYearId): void
    {
        $termId = $this->nullableId($data['term_id'] ?? null);

        if ($termId === null) {
            return;
        }

        $term = Term::query()->find($termId);

        if ($term === null || (int) $term->academic_year_id !== $academicYearId) {
            throw ValidationException::withMessages([
                'term_id' => 'Term yang dipilih bukan bagian dari tahun ajaran tersebut.',
            ]);
        }
    }

    private function assertTeachingWindowIsFree(
        int $teacherId,
        int $classId,
        int $subjectId,
        ?int $exceptAssignmentId = null,
    ): void {
        $conflict = TeachingAssignment::query()
            ->where('teacher_id', $teacherId)
            ->where('class_id', $classId)
            ->where('subject_id', $subjectId)
            ->active()
            ->when($exceptAssignmentId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptAssignmentId))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'subject_id' => 'Guru ini sudah memilikiassignment aktif untuk mata pelajaran tersebut di kelas yang sama.',
            ]);
        }
    }

    private function assertHomeroomWindowIsFree(int $classId, int $teacherId, ?int $exceptAssignmentId = null): void
    {
        $conflict = ClassTeacherAssignment::query()
            ->where('class_id', $classId)
            ->where('teacher_id', $teacherId)
            ->active()
            ->when($exceptAssignmentId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptAssignmentId))
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Guru ini sudah menjadi wali kelas aktif untuk kelas tersebut.',
            ]);
        }
    }

    private function blankToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function nullableId(mixed $value): ?int
    {
        $trimmed = $this->blankToNull($value);

        return $trimmed === null ? null : (int) $trimmed;
    }
}
