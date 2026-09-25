<?php

namespace App\Policies;

use App\Models\GalleryAlbum;
use App\Models\User;

class GalleryAlbumPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, GalleryAlbum $galleryAlbum): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, GalleryAlbum $galleryAlbum): bool
    {
        return $this->manages($user);
    }

    public function publish(User $user, GalleryAlbum $galleryAlbum): bool
    {
        return $this->manages($user);
    }

    public function unpublish(User $user, GalleryAlbum $galleryAlbum): bool
    {
        return $this->manages($user);
    }

    public function archive(User $user, GalleryAlbum $galleryAlbum): bool
    {
        return $this->manages($user);
    }

    public function delete(User $user, GalleryAlbum $galleryAlbum): bool
    {
        return false;
    }

    private function manages(User $user): bool
    {
        return $user->isActive() && $user->hasVerifiedEmail() && $user->isAdministrator();
    }
}
