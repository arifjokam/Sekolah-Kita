<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentLifecycleStatus;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Administrator write path for the student master record.
 *
 * Student, Guardian, StudentGuardian and Enrollment stay separate domain
 * concepts: the service links them through the existing pivot models and never
 * flattens a relationship into a free text column. A portal login is only
 * created when the administrator explicitly asks for one, because the
 * architecture treats the login as a separate identity from the student record.
 */
class StudentAdministrationService
{
    /**
     * @var list<string>
     */
    private const WRITABLE = [
        'legal_name',
        'preferred_name',
        'student_number',
        'status',
        'admission_date',
        'graduation_date',
        'leaving_date',
        'leaving_reason',
        'birth_date',
        'birth_place',
        'gender',
        'national_id',
        'nisn',
        'address',
        'phone',
        'email',
    ];

    public function __construct(
        private readonly AuditLogService $audit,
        private readonly StudentNumberAllocator $studentNumbers,
        private readonly EnrollmentService $enrollments,
    ) {}

    /**
     * @param  array{search?: string|null, status?: string|null, class_id?: int|null, academic_year_id?: int|null}  $filters
     * @return Builder<Student>
     */
    public function listing(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $classId = (int) ($filters['class_id'] ?? 0);
        $academicYearId = (int) ($filters['academic_year_id'] ?? 0);

        return Student::query()
            ->with(['user:id,name,email,status'])
            ->withCount(['enrollments as enrollments_count', 'guardians as guardians_count'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('legal_name', 'like', $like)
                        ->orWhere('preferred_name', 'like', $like)
                        ->orWhere('student_number', 'like', $like)
                        ->orWhere('nisn', 'like', $like);
                });
            })
            ->when($status !== '', fn (Builder $query): Builder => $query->where('status', $status))
            ->when($classId > 0, fn (Builder $query): Builder => $query
                ->whereHas('enrollments', fn (Builder $query): Builder => $query
                    ->where('class_id', $classId)
                    ->where('status', EnrollmentStatus::Active->value)))
            ->when($academicYearId > 0, fn (Builder $query): Builder => $query
                ->whereHas('enrollments', fn (Builder $query): Builder => $query
                    ->where('academic_year_id', $academicYearId)))
            ->orderBy('legal_name')
            ->orderBy('id');
    }

    /**
     * Create the student and, when the administrator asked for it, the initial
     * enrollment and the first guardian in one transaction.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $enrollment
     * @param  array<string, mixed>|null  $guardian
     */
    public function create(User $actor, array $data, ?array $enrollment = null, ?array $guardian = null): Student
    {
        Gate::forUser($actor)->authorize('create', Student::class);

        $student = DB::transaction(function () use ($actor, $data, $enrollment, $guardian): Student {
            $attributes = $this->filter($data);

            if (blank($attributes['student_number'] ?? null)) {
                $attributes['student_number'] = $this->studentNumbers->allocate();
            }

            $student = Student::query()->create($attributes);

            $this->audit->recordOrFail($actor, 'student.created', 'student', $student->getKey(), context: [
                'status' => $student->status?->value,
            ]);

            if ($guardian !== null) {
                $this->attachGuardian($actor, $student, $guardian);
            }

            if ($enrollment !== null) {
                $this->enroll($actor, $student, $enrollment);
            }

            return $student;
        });

        return $student->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Student $student, array $data): Student
    {
        Gate::forUser($actor)->authorize('update', $student);

        return DB::transaction(function () use ($actor, $student, $data): Student {
            $student->fill($this->filter($data))->save();

            $this->audit->recordOrFail($actor, 'student.updated', 'student', $student->getKey(), context: [
                'status' => $student->status?->value,
            ]);

            return $student;
        })->refresh();
    }

    /**
     * Move a student through the lifecycle states and keep the portal account
     * in step so a graduated or withdrawn student cannot keep signing in.
     */
    public function changeLifecycleStatus(User $actor, Student $student, StudentLifecycleStatus $status): Student
    {
        Gate::forUser($actor)->authorize('update', $student);

        if ($student->status === $status) {
            return $student;
        }

        if (in_array($status, [StudentLifecycleStatus::Graduated, StudentLifecycleStatus::Withdrawn, StudentLifecycleStatus::Transferred], true)) {
            $this->assertNoActiveEnrollment($student);
        }

        return DB::transaction(function () use ($actor, $student, $status): Student {
            $student->forceFill([
                'status' => $status,
                'graduation_date' => $status === StudentLifecycleStatus::Graduated
                    ? ($student->graduation_date ?? today()->toDateString())
                    : $student->graduation_date,
                'leaving_date' => in_array($status, [StudentLifecycleStatus::Withdrawn, StudentLifecycleStatus::Transferred], true)
                    ? ($student->leaving_date ?? today()->toDateString())
                    : $student->leaving_date,
            ])->save();

            $user = $student->user;
            if ($user instanceof User) {
                $next = $student->isActive() ? AccountStatus::Active : AccountStatus::Inactive;

                if ($user->status !== $next) {
                    $user->forceFill(['status' => $next])->save();
                }
            }

            $this->audit->recordOrFail($actor, 'student.lifecycle_status_changed', 'student', $student->getKey(), context: [
                'status' => $status->value,
            ]);

            return $student;
        })->refresh();
    }

    /**
     * Create or reuse a guardian and link it to the student.
     *
     * @param  array<string, mixed>  $data
     */
    public function attachGuardian(User $actor, Student $student, array $data): StudentGuardian
    {
        Gate::forUser($actor)->authorize('update', $student);

        return DB::transaction(function () use ($actor, $student, $data): StudentGuardian {
            $phone = $this->blankToNull($data['phone'] ?? null);
            $email = $this->blankToNull($data['email'] ?? null);

            $guardian = null;

            if ($phone !== null) {
                $guardian = Guardian::query()
                    ->where('phone', $phone)
                    ->where('status', 'active')
                    ->first();
            }

            if ($guardian === null && $email !== null) {
                $guardian = Guardian::query()
                    ->where('email', $email)
                    ->where('status', 'active')
                    ->first();
            }

            $guardian ??= Guardian::query()->create([
                'legal_name' => (string) $data['legal_name'],
                'phone' => $phone,
                'email' => $email,
                'address' => $this->blankToNull($data['address'] ?? null),
                'national_id' => $this->blankToNull($data['national_id'] ?? null),
                'status' => 'active',
            ]);

            if ($data['is_primary_contact'] ?? false) {
                $this->clearOtherPrimaryContacts($student, null);
            }

            $link = StudentGuardian::query()->create([
                'student_id' => $student->getKey(),
                'guardian_id' => $guardian->getKey(),
                'relationship_type' => (string) $data['relationship_type'],
                'is_primary_contact' => (bool) ($data['is_primary_contact'] ?? false),
                'is_emergency_contact' => (bool) ($data['is_emergency_contact'] ?? false),
                'priority_order' => $data['priority_order'] ?? null,
                'starts_on' => (string) ($data['starts_on'] ?? today()->toDateString()),
                'ends_on' => $this->blankToNull($data['ends_on'] ?? null),
                'relationship_note' => $this->blankToNull($data['relationship_note'] ?? null),
            ]);

            $this->audit->recordOrFail($actor, 'student.guardian_linked', 'student', $student->getKey(), context: [
                'relationship_type' => (string) $data['relationship_type'],
            ]);

            return $link;
        });
    }

    public function detachGuardian(User $actor, Student $student, StudentGuardian $link): void
    {
        Gate::forUser($actor)->authorize('update', $student);

        if ((int) $link->student_id !== (int) $student->getKey()) {
            throw ValidationException::withMessages([
                'guardian' => 'Wali tersebut tidak terhubung dengan siswa ini.',
            ]);
        }

        DB::transaction(function () use ($actor, $student, $link): void {
            $link->forceFill(['ends_on' => $link->ends_on ?? today()->toDateString()])->save();

            $this->audit->recordOrFail($actor, 'student.guardian_unlinked', 'student', $student->getKey(), context: [
                'relationship_type' => $link->relationship_type?->value,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $enrollment
     */
    public function enroll(User $actor, Student $student, array $enrollment): Enrollment
    {
        $class = ClassModel::query()->findOrFail((int) $enrollment['class_id']);
        $enrolledOn = (string) ($enrollment['enrolled_on'] ?? today()->toDateString());

        return $this->enrollments->create(
            actor: $actor,
            student: $student,
            class: $class,
            enrolledOn: $enrolledOn,
            effectiveFrom: $this->blankToNull($enrollment['effective_from'] ?? null) ?? $enrolledOn,
            transitionReason: $this->blankToNull($enrollment['transition_reason'] ?? null),
        );
    }

    private function assertNoActiveEnrollment(Student $student): void
    {
        $active = Enrollment::query()
            ->where('student_id', $student->getKey())
            ->active()
            ->exists();

        if ($active) {
            throw ValidationException::withMessages([
                'status' => 'Siswa masih memiliki enrollment aktif. Akhiri atau pindahkan enrollment terlebih dahulu.',
            ]);
        }
    }

    private function clearOtherPrimaryContacts(Student $student, ?int $exceptLinkId): void
    {
        StudentGuardian::query()
            ->where('student_id', $student->getKey())
            ->where('is_primary_contact', true)
            ->when($exceptLinkId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptLinkId))
            ->update(['is_primary_contact' => false]);
    }

    private function blankToNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return $value === null ? null : (string) $value;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filter(array $data): array
    {
        return array_intersect_key($data, array_flip(self::WRITABLE));
    }
}
