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

    private function assigned(User $user, ApplicationDocument $document): bool
    {
        $application = $document->application;

        return $user->isActive()
            && $user->hasVerifiedEmail()
            && $user->isAdministrator()
            && $application !== null
            && (int) $application->assigned_reviewer_user_id === (int) $user->getKey();
    }
}
