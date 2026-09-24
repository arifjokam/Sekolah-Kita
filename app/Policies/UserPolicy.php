<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->isAdministrator($user) || $user->is($model);
    }

    public function update(User $user, User $model): bool
    {
        return $this->isAdministrator($user) || $user->is($model);
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function activate(User $user, User $model): bool
    {
        return $this->isAdministrator($user);
    }

    public function suspend(User $user, User $model): bool
    {
        return $this->isAdministrator($user);
    }

    public function deactivate(User $user, User $model): bool
    {
        return $this->isAdministrator($user);
    }

    public function assignRole(User $user, User $model): bool
    {
        return $this->isAdministrator($user);
    }

    private function isAdministrator(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }
}
