<?php

namespace App\Policies;

use App\Models\ClassModel;
use App\Models\User;

class ClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && ($user->isAdministrator() || $user->isGuru() || $user->isSiswa());
    }

    public function view(User $user, ClassModel $class): bool
    {
        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isSiswa()) {
            $student = $user->student;
            if ($student) {
                $currentEnrollment = $student->currentEnrollment();

                return $currentEnrollment && $currentEnrollment->class_id === $class->id;
            }

            return false;
        }

        if ($user->isGuru()) {
            $teacher = $user->teacher;
            if (! $teacher || ! $teacher->isActive()) {
                return false;
            }

            $hasHomeroom = $teacher->activeClassTeacherAssignments()
                ->where('class_id', $class->id)
                ->exists();

            $hasTeachingAssignment = $teacher->activeTeachingAssignments()
                ->where('class_id', $class->id)
                ->exists();

            return $hasHomeroom || $hasTeachingAssignment;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    public function update(User $user, ClassModel $class): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    public function delete(User $user, ClassModel $class): bool
    {
        return false;
    }

    public function manage(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    public function viewRoster(User $user, ClassModel $class): bool
    {
        return $this->view($user, $class);
    }
}
