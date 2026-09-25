<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, ContactMessage $contactMessage): bool
    {
        return $this->manages($user);
    }

    public function updateStatus(User $user, ContactMessage $contactMessage): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user, ContactMessage $contactMessage): bool
    {
        return false;
    }

    private function manages(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }
}
