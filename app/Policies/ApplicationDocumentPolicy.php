<?php

namespace App\Policies;

use App\Models\ApplicationDocument;
use App\Models\User;

class ApplicationDocumentPolicy
{
    public function view(User $user, ApplicationDocument $document): bool
    {
        return $this->assigned($user, $document);
    }

    public function downloadPrivate(User $user, ApplicationDocument $document): bool
    {
        return $this->assigned($user, $document);
    }

    public function verify(User $user, ApplicationDocument $document): bool
    {
        return $this->assigned($user, $document);
    }

    public function reject(User $user, ApplicationDocument $document): bool
    {
        return $this->assigned($user, $document);
    }

    public function requestRevision(User $user, ApplicationDocument $document): bool
    {
        return $this->assigned($user, $document);
    }

    /*
    |--------------------------------------------------------------------------
    | Applicant portal
    |--------------------------------------------------------------------------
    */

    /**
     * The applicant may see the metadata of their own document.
     */
    public function viewOwn(User $user, ApplicationDocument $document): bool
    {
        return $this->isOwnDocument($user, $document);
    }

    /**
     * The applicant may download their own document.
     *
     * This is the one path on which a candidate receives document bytes, and it
     * is streamed by the controller through the same private-disk gate the
     * reviewer uses, never from a public URL.
     */
    public function downloadOwn(User $user, ApplicationDocument $document): bool
    {
        return $this->isOwnDocument($user, $document);
    }

    public function replaceOwn(User $user, ApplicationDocument $document): bool
    {
        return $this->isOwnDocument($user, $document)
            && $document->application !== null
            && $document->application->canAcceptDocument();
    }

    private function assigned(User $user, ApplicationDocument $document): bool
    {
        $application = $document->application;

        return $user->isActive()
            && $user->hasVerifiedEmail()
            && $user->isAdministrator()
            && $application !== null
            && (int) $application->assigned_reviewer_user_id === (int) $user->getKey();
    }

    private function isOwnDocument(User $user, ApplicationDocument $document): bool
    {
        $application = $document->application;

        return $user->isActive()
            && $user->hasVerifiedEmail()
            && $user->isCalon()
            && $application !== null
            && $application->applicant_user_id !== null
            && (int) $application->applicant_user_id === (int) $user->getKey();
    }
}
