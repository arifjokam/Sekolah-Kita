<?php

namespace App\Policies;

use App\Models\Teacher;
use App\Models\User;

class TeacherPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && ($user->isAdministrator() || $user->isGuru());
    }

    public function view(User $user, Teacher $teacher): bool
    {
        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isGuru() && $user->teacher?->id === $teacher->id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    public function update(User $user, Teacher $teacher): bool
    {
        if (! $user->isActive() || ! $user->hasVerifiedEmail()) {
            return false;
        }

        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->isGuru() && $user->teacher?->id === $teacher->id) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Teacher $teacher): bool
    {
        return false;
    }

    public function manageAssignments(User $user, Teacher $teacher): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    public function managePublicProfile(User $user, Teacher $teacher): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    public function publishPublicProfile(User $user, Teacher $teacher): bool
    {
        return $this->managePublicProfile($user, $teacher);
    }
}
