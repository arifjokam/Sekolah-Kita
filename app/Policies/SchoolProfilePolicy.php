<?php

namespace App\Policies;

use App\Models\SchoolProfile;
use App\Models\User;

class SchoolProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    public function update(User $user, SchoolProfile $schoolProfile): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    public function delete(User $user, SchoolProfile $schoolProfile): bool
    {
        return false;
    }
}
