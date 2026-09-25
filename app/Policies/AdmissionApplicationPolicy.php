<?php

namespace App\Policies;

use App\Models\AdmissionApplication;
use App\Models\User;

class AdmissionApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    public function view(User $user, AdmissionApplication $application): bool
    {
        return $this->isAdministrator($user);
    }

    public function assignReviewer(User $user, AdmissionApplication $application): bool
    {
        return $this->isAdministrator($user);
    }

    public function review(User $user, AdmissionApplication $application): bool
    {
        return $this->isAssignedReviewer($user, $application);
    }

    public function requestRevision(User $user, AdmissionApplication $application): bool
    {
        return $this->isAssignedReviewer($user, $application);
    }

    public function decide(User $user, AdmissionApplication $application): bool
    {
        return $this->isAssignedReviewer($user, $application);
    }

    public function convert(User $user, AdmissionApplication $application): bool
    {
        return $this->isAssignedReviewer($user, $application);
    }

    public function viewInternalHistory(User $user, AdmissionApplication $application): bool
    {
        return $this->isAssignedReviewer($user, $application);
    }

    public function delete(User $user, AdmissionApplication $application): bool
    {
        return false;
    }

    private function isAdministrator(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }

    private function isAssignedReviewer(User $user, AdmissionApplication $application): bool
    {
        return $this->isAdministrator($user)
            && (int) $application->assigned_reviewer_user_id === (int) $user->getKey();
    }
}
