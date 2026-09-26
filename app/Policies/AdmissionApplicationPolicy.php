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

    /*
    |--------------------------------------------------------------------------
    | Applicant portal
    |--------------------------------------------------------------------------
    |
    | The portal abilities are the only way a calon account reaches an
    | application, and every one of them resolves through applicant_user_id. A
    | portal account is never an administrator, a teacher or a student, so these
    | cannot be reached by a role that already has reviewer powers.
    |
    */

    public function viewOwn(User $user, AdmissionApplication $application): bool
    {
        return $this->isOwnApplication($user, $application);
    }

    public function updateOwn(User $user, AdmissionApplication $application): bool
    {
        return $this->isOwnApplication($user, $application) && $application->isPortalEditable();
    }

    public function submitOwn(User $user, AdmissionApplication $application): bool
    {
        return $this->isOwnApplication($user, $application) && $application->isDraft();
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

    /**
     * Ownership, and nothing else.
     *
     * The comparison is a strict integer id match on the foreign key, so no
     * route parameter, query string or request body can widen it. A portal
     * account that is somehow also an administrator still only owns the
     * applications its own id points at through this path.
     */
    private function isOwnApplication(User $user, AdmissionApplication $application): bool
    {
        return $user->isActive()
            && $user->hasVerifiedEmail()
            && $user->isCalon()
            && $application->applicant_user_id !== null
            && (int) $application->applicant_user_id === (int) $user->getKey();
    }
}
