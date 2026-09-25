<?php

namespace App\Policies;

use App\Models\NewsArticle;
use App\Models\User;

class NewsArticlePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, NewsArticle $newsArticle): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, NewsArticle $newsArticle): bool
    {
        return $this->manages($user);
    }

    public function publish(User $user, NewsArticle $newsArticle): bool
    {
        return $this->manages($user);
    }

    public function unpublish(User $user, NewsArticle $newsArticle): bool
    {
        return $this->manages($user);
    }

    public function archive(User $user, NewsArticle $newsArticle): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user, NewsArticle $newsArticle): bool
    {
        return false;
    }

    private function manages(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }
}
