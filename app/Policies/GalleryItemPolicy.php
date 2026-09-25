<?php

namespace App\Policies;

use App\Models\GalleryItem;
use App\Models\User;

class GalleryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, GalleryItem $galleryItem): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, GalleryItem $galleryItem): bool
    {
        return $this->manages($user);
    }

    public function publish(User $user, GalleryItem $galleryItem): bool
    {
        return $this->manages($user);
    }

    public function unpublish(User $user, GalleryItem $galleryItem): bool
    {
        return $this->manages($user);
    }

    public function hide(User $user, GalleryItem $galleryItem): bool
    {
        return $this->manages($user);
    }

    public function archive(User $user, GalleryItem $galleryItem): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user, GalleryItem $galleryItem): bool
    {
        return false;
    }

    private function manages(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }
}
