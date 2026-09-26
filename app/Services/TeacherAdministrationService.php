<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\TeacherEmploymentStatus;
use App\Enums\UserRole;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Administrator write path for the teacher master record.
 *
 * Identity is never duplicated: the teacher row only points at a `users` row
 * through `user_id`, and the login identity is provisioned separately through
 * AccountLifecycleService. A teacher without a `user_id` is a staff record with
 * no portal access, which is the normal state for a teacher the school has not
 * given an account to.
 */
class TeacherAdministrationService
{
    /**
     * Fields the administrator may write from the teacher master form.
     *
     * @var list<string>
     */
    private const WRITABLE = [
        'legal_name',
        'preferred_name',
        'employee_number',
        'employment_status',
        'employment_start_date',
        'employment_end_date',
        'position_title',
        'qualification_summary',
        'biography',
        'birth_date',
        'birth_place',
        'gender',
        'national_id',
        'address',
        'phone',
        'email',
    ];

    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Build the searchable, filterable teacher list.
     *
     * @param  array{search?: string|null, status?: string|null}  $filters
     * @return Builder<Teacher>
     */
    public function listing(array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        return Teacher::query()
            ->with(['user:id,name,email,status'])
            ->withCount([
                'teachingAssignments as teaching_assignments_count' => fn (Builder $query): Builder => $query->active(),
                'classTeacherAssignments as class_teacher_assignments_count' => fn (Builder $query): Builder => $query->active(),
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->where('legal_name', 'like', $like)
                        ->orWhere('preferred_name', 'like', $like)
                        ->orWhere('employee_number', 'like', $like)
                        ->orWhere('position_title', 'like', $like);
                });
            })
            ->when($status !== '', fn (Builder $query): Builder => $query->where('employment_status', $status))
            ->orderBy('legal_name')
            ->orderBy('id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Teacher
    {
        Gate::forUser($actor)->authorize('create', Teacher::class);

        $teacher = DB::transaction(function () use ($actor, $data): Teacher {
            $teacher = Teacher::query()->create($this->filter($data));

            $this->audit->recordOrFail($actor, 'teacher.created', 'teacher', $teacher->getKey(), context: [
                'employment_status' => $teacher->employment_status?->value,
                'has_login_identity' => $teacher->user_id !== null,
            ]);

            return $teacher;
        });

        return $teacher->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Teacher $teacher, array $data): Teacher
    {
        Gate::forUser($actor)->authorize('update', $teacher);

        return DB::transaction(function () use ($actor, $teacher, $data): Teacher {
            $teacher->fill($this->filter($data))->save();

            $this->audit->recordOrFail($actor, 'teacher.updated', 'teacher', $teacher->getKey(), context: [
                'employment_status' => $teacher->employment_status?->value,
            ]);

            return $teacher;
        })->refresh();
    }

    /**
     * Move a teacher between employment states.
     *
     * The linked login account is kept in step so an administrator can never
     * leave a suspended teacher with a working portal session, and so the
     * employment state shown on the master list matches what the guard allows.
     */
    public function changeEmploymentStatus(User $actor, Teacher $teacher, TeacherEmploymentStatus $status): Teacher
    {
        Gate::forUser($actor)->authorize('update', $teacher);

        if ($teacher->employment_status === $status) {
            return $teacher;
        }

        if ($status === TeacherEmploymentStatus::Active
            && $teacher->employment_start_date !== null
            && $teacher->employment_start_date->isFuture()) {
            throw ValidationException::withMessages([
                'employment_status' => 'Tanggal mulai kerja belum tercapai.',
            ]);
        }

        return DB::transaction(function () use ($actor, $teacher, $status): Teacher {
            $teacher->forceFill([
                'employment_status' => $status,
                'employment_end_date' => in_array($status, [TeacherEmploymentStatus::Inactive, TeacherEmploymentStatus::Retired], true)
                    ? ($teacher->employment_end_date ?? today()->toDateString())
                    : $teacher->employment_end_date,
            ])->save();

            $this->synchronizeAccountStatus($actor, $teacher);

            $this->audit->recordOrFail($actor, 'teacher.employment_status_changed', 'teacher', $teacher->getKey(), context: [
                'employment_status' => $status->value,
            ]);

            return $teacher;
        })->refresh();
    }

    /**
     * Keep the optional login account aligned with the employment state.
     *
     * Teachers are deactivated rather than suspended: a retired or inactive
     * teacher is no longer an active member of staff, and a suspended account
     * implies a temporary state that this domain does not model.
     */
    private function synchronizeAccountStatus(User $actor, Teacher $teacher): void
    {
        $user = $teacher->user;

        if (! $user instanceof User) {
            return;
        }

        $shouldBeActive = $teacher->employment_status === TeacherEmploymentStatus::Active;

        if ($user->role !== UserRole::Guru) {
            $user->forceFill(['role' => UserRole::Guru])->save();
        }

        $next = $shouldBeActive ? AccountStatus::Active : AccountStatus::Inactive;

        if ($user->status !== $next) {
            $this->audit->recordOrFail($actor, 'teacher.account_status_changed', 'user', $user->getKey(), context: [
                'status' => $next->value,
                'teacher_id' => $teacher->getKey(),
            ]);

            $user->forceFill(['status' => $next])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filter(array $data): array
    {
        $writable = array_flip(self::WRITABLE);

        return array_intersect_key($data, $writable);
    }
}
