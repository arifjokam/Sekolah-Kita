<?php

namespace App\Policies;

use App\Models\Achievement;
use App\Models\User;

class AchievementPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, Achievement $achievement): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, Achievement $achievement): bool
    {
        return $this->manages($user);
    }

    public function publish(User $user, Achievement $achievement): bool
    {
        return $this->manages($user);
    }

    public function unpublish(User $user, Achievement $achievement): bool
    {
        return $this->manages($user);
    }

    public function archive(User $user, Achievement $achievement): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user, Achievement $achievement): bool
    {
        return false;
    }

    private function manages(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }
}
