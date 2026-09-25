<?php

namespace App\Policies;

use App\Models\AdmissionPeriod;
use App\Models\User;

class AdmissionPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, AdmissionPeriod $admissionPeriod): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, AdmissionPeriod $admissionPeriod): bool
    {
        return $this->manages($user);
    }

    public function open(User $user, AdmissionPeriod $admissionPeriod): bool
    {
        return $this->manages($user);
    }

    public function close(User $user, AdmissionPeriod $admissionPeriod): bool
    {
        return $this->manages($user);
    }

    public function archive(User $user, AdmissionPeriod $admissionPeriod): bool
    {
        return $this->manages($user);
    }

    private function manages(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }
}
