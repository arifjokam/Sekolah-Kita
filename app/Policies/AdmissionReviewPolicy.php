<?php

namespace App\Policies;

use App\Models\AdmissionReview;
use App\Models\User;

class AdmissionReviewPolicy
{
    public function view(User $user, AdmissionReview $review): bool
    {
        return $user->isActive()
            && $user->hasVerifiedEmail()
            && $user->isAdministrator()
            && (int) $review->reviewer_user_id === (int) $user->getKey();
    }
}
