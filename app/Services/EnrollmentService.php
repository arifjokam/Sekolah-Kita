<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EnrollmentService
{
    /**
     * Create an active enrollment while preserving the one-per-year invariant.
     */
    public function create(
        User $actor,
        Student $student,
        ClassModel $class,
        string $enrolledOn,
        ?string $effectiveFrom = null,
        ?string $transitionReason = null,
    ): Enrollment {
        Gate::forUser($actor)->authorize('create', Enrollment::class);
        Gate::forUser($actor)->authorize('manageEnrollments', $student);

        return DB::transaction(function () use ($student, $class, $enrolledOn, $effectiveFrom, $transitionReason): Enrollment {
            $lockedStudent = $this->lockStudent($student);
            $lockedClass = $this->lockClass($class);

            $enrollment = new Enrollment([
                'student_id' => $lockedStudent->getKey(),
                'class_id' => $lockedClass->getKey(),
                'academic_year_id' => $lockedClass->academic_year_id,
                'status' => EnrollmentStatus::Active,
                'enrolled_on' => $enrolledOn,
                'effective_from' => $effectiveFrom ?? $enrolledOn,
                'effective_to' => null,
                'completed_on' => null,
                'transition_reason' => $transitionReason,
            ]);

            $this->assertNoActiveEnrollment(
                (int) $lockedStudent->getKey(),
                (int) $lockedClass->academic_year_id,
            );
            $enrollment->save();

            return $enrollment;
        }, attempts: 3);
    }

    /**
     * End the current placement and create its replacement in one transaction.
     */
    public function transfer(
        User $actor,
        Enrollment $enrollment,
        ClassModel $targetClass,
        string $effectiveOn,
        ?string $transitionReason = null,
    ): Enrollment {
        Gate::forUser($actor)->authorize('transfer', $enrollment);

        return DB::transaction(function () use ($actor, $enrollment, $targetClass, $effectiveOn, $transitionReason): Enrollment {
            $lockedStudent = Student::query()
                ->whereKey($enrollment->student_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedEnrollment = Enrollment::query()
                ->whereKey($enrollment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedEnrollment->student_id !== (int) $lockedStudent->getKey()) {
                throw (new ModelNotFoundException)->setModel(Enrollment::class, [$enrollment->getKey()]);
            }

            Gate::forUser($actor)->authorize('manageEnrollments', $lockedStudent);

            if (! $lockedEnrollment->isActive()) {
                throw ValidationException::withMessages([
                    'enrollment' => 'Only a current active enrollment can be transferred.',
                ]);
            }

            $lockedTargetClass = $this->lockClass($targetClass);
            if ((int) $lockedTargetClass->getKey() === (int) $lockedEnrollment->class_id) {
                throw ValidationException::withMessages([
                    'class_id' => 'The student is already enrolled in this class.',
                ]);
            }

            if ((int) $lockedTargetClass->academic_year_id !== (int) $lockedEnrollment->academic_year_id) {
                throw ValidationException::withMessages([
                    'academic_year_id' => 'A transfer must remain within the same academic year.',
                ]);
            }

            $newEnrollment = new Enrollment([
                'student_id' => $lockedStudent->getKey(),
                'class_id' => $lockedTargetClass->getKey(),
                'academic_year_id' => $lockedTargetClass->academic_year_id,
                'status' => EnrollmentStatus::Active,
                'enrolled_on' => $effectiveOn,
                'effective_from' => $effectiveOn,
                'effective_to' => null,
                'completed_on' => null,
                'transition_reason' => null,
            ]);

            $this->assertNoActiveEnrollment(
                (int) $lockedStudent->getKey(),
                (int) $lockedTargetClass->academic_year_id,
                (int) $lockedEnrollment->getKey(),
            );

            $lockedEnrollment->forceFill([
                'status' => EnrollmentStatus::Transferred,
                'effective_to' => $effectiveOn,
                'transition_reason' => $transitionReason,
            ])->save();

            $newEnrollment->save();

            return $newEnrollment;
        }, attempts: 3);
    }

    private function assertNoActiveEnrollment(
        int $studentId,
        int $academicYearId,
        ?int $exceptEnrollmentId = null,
    ): void {
        $query = Enrollment::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', EnrollmentStatus::Active->value)
            ->when($exceptEnrollmentId !== null, function (Builder $query) use ($exceptEnrollmentId): void {
                $query->where('id', '!=', $exceptEnrollmentId);
            })
            ->lockForUpdate();

        if ($query->first() !== null) {
            throw ValidationException::withMessages([
                'enrollment' => 'The student already has an active enrollment for this academic year.',
            ]);
        }
    }

    private function lockStudent(Student $student): Student
    {
        return Student::query()
            ->whereKey($student->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockClass(ClassModel $class): ClassModel
    {
        return ClassModel::query()
            ->whereKey($class->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }
}
