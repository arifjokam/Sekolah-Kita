<?php

namespace App\Policies;

use App\Models\AdmissionApplication;
use App\Models\ApplicationGuardian;
use App\Models\User;

/**
 * Guardians attached to a PPDB application.
 *
 * A guardian row is reachable in exactly two ways: the assigned administrator
 * reviewer, and the applicant portal account that owns the parent application.
 * There is deliberately no "view any guardian" ability, because there is no
 * guardian index screen and therefore no legitimate way to enumerate them.
 *
 * Every ability resolves ownership from the *parent application*, never from the
 * guardian row alone, so swapping a guardian id between two applications cannot
 * widen what an account may change.
 */
class ApplicationGuardianPolicy
{
    public function view(User $user, ApplicationGuardian $guardian): bool
    {
        $application = $guardian->application;

        if ($application === null) {
            return false;
        }

        return $this->isOwnApplication($user, $application) || $this->isAssignedReviewer($user, $application);
    }

    /**
     * Creating a guardian on a given application.
     *
     * The application is the second argument because a guardian only ever exists
     * inside one, and the ownership check is made against that parent rather
     * than against a not-yet-created child.
     */
    public function create(User $user, AdmissionApplication $application): bool
    {
        return $this->isOwnApplication($user, $application) || $this->isAssignedReviewer($user, $application);
    }

    public function update(User $user, ApplicationGuardian $guardian): bool
    {
        $application = $guardian->application;

        if ($application === null) {
            return false;
        }

        return $this->isOwnApplication($user, $application) || $this->isAssignedReviewer($user, $application);
    }

    public function delete(User $user, ApplicationGuardian $guardian): bool
    {
        // Never for the applicant: removing a guardian mid-application would let
        // a required section be silently emptied after documents were uploaded.
        $application = $guardian->application;

        return $application !== null && $this->isAssignedReviewer($user, $application);
    }

    private function isOwnApplication(User $user, AdmissionApplication $application): bool
    {
        return $user->isActive()
            && $user->hasVerifiedEmail()
            && $user->isCalon()
            && $application->applicant_user_id !== null
            && (int) $application->applicant_user_id === (int) $user->getKey()
            && $application->isPortalEditable();
    }

    private function isAssignedReviewer(User $user, AdmissionApplication $application): bool
    {
        return $user->isActive()
            && $user->hasVerifiedEmail()
            && $user->isAdministrator()
            && (int) $application->assigned_reviewer_user_id === (int) $user->getKey();
    }
}
