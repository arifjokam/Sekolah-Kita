<?php

namespace App\Policies;

use App\Models\NotificationPreference;
use App\Models\User;

class NotificationPreferencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail();
    }

    public function view(User $user, NotificationPreference $notificationPreference): bool
    {
        return $this->owns($user, $notificationPreference);
    }

    public function create(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail();
    }

    public function update(User $user, NotificationPreference $notificationPreference): bool
    {
        return $this->owns($user, $notificationPreference);
    }

    public function delete(User $user, NotificationPreference $notificationPreference): bool
    {
        return false;
    }

    private function owns(User $user, NotificationPreference $preference): bool
    {
        return $user->isActive()
            && $user->hasVerifiedEmail()
            && (int) $preference->user_id === (int) $user->getKey();
    }
}
