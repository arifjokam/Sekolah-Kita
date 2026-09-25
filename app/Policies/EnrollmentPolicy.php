<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && ($user->isAdministrator() || $user->isGuru() || $user->isSiswa());
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isSiswa() && $user->student?->id === $enrollment->student_id) {
            return true;
        }

        if ($user->isGuru()) {
            $teacher = $user->teacher;
            if (! $teacher || ! $teacher->isActive()) {
                return false;
            }

            $hasHomeroom = $teacher->activeClassTeacherAssignments()
                ->where('class_id', $enrollment->class_id)
                ->exists();

            $hasTeachingAssignment = $teacher->activeTeachingAssignments()
                ->where('class_id', $enrollment->class_id)
                ->exists();

            return $hasHomeroom || $hasTeachingAssignment;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return false;
    }

    public function manage(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    public function transfer(User $user, Enrollment $enrollment): bool
    {
        return $this->manage($user);
    }
}
