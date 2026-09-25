<?php

namespace App\Services;

use App\Enums\GalleryItemStatus;
use App\Enums\PublicationStatus;
use App\Models\Achievement;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\MediaAsset;
use App\Models\NewsArticle;
use App\Models\SchoolProfile;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PublicContentManager
{
    public function __construct(
        private readonly MediaAssetService $mediaAssets,
        private readonly PublicContentCache $cache,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveSchoolProfile(
        User $actor,
        ?SchoolProfile $schoolProfile,
        array $data,
        ?UploadedFile $logo,
        ?UploadedFile $hero,
    ): SchoolProfile {
        if ($schoolProfile === null) {
            Gate::forUser($actor)->authorize('create', SchoolProfile::class);
        } else {
            Gate::forUser($actor)->authorize('update', $schoolProfile);
        }

        $payload = Arr::only($data, [
            'legal_name',
            'public_name',
            'short_name',
            'address_line',
            'city',
            'postal_code',
            'public_phone',
            'public_email',
            'vision',
            'mission',
            'about',
        ]);
        $oldLogoId = $schoolProfile?->logo_media_id;
        $oldHeroId = $schoolProfile?->hero_media_id;
        $newLogo = $this->storeOptionalMedia($logo, $actor);
        $newHero = $this->storeOptionalMedia($hero, $actor);

        if ($schoolProfile === null) {
            $schoolProfile = SchoolProfile::query()->create([
                ...$payload,
                'is_singleton' => true,
                'logo_media_id' => $newLogo?->getKey(),
                'hero_media_id' => $newHero?->getKey(),
            ]);
        } else {
            if ($this->flag($data, 'remove_logo')) {
                $payload['logo_media_id'] = null;
            }

            if ($this->flag($data, 'remove_hero')) {
                $payload['hero_media_id'] = null;
            }

            if ($newLogo !== null) {
                $payload['logo_media_id'] = $newLogo->getKey();
            }

            if ($newHero !== null) {
                $payload['hero_media_id'] = $newHero->getKey();
            }

            $schoolProfile->fill($payload)->save();
        }

        $this->synchronizeMedia($newLogo);
        $this->synchronizeMedia($newHero);
        $this->withdrawReplacedMedia($oldLogoId, $schoolProfile->logo_media_id);
        $this->withdrawReplacedMedia($oldHeroId, $schoolProfile->hero_media_id);

        $this->cache->invalidate('school-profile');
        $this->logChange($actor, 'school_profile', $schoolProfile, 'updated');

        return $schoolProfile->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createNews(User $actor, array $data, ?UploadedFile $cover): NewsArticle
    {
        Gate::forUser($actor)->authorize('create', NewsArticle::class);

        $payload = Arr::only($data, ['title', 'slug', 'excerpt', 'body']);
        $media = $this->storeOptionalMedia($cover, $actor);
        $article = NewsArticle::query()->create([
            ...$payload,
            'author_user_id' => $actor->getKey(),
            'cover_media_id' => $media?->getKey(),
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'archived_at' => null,
        ]);

        $this->cache->invalidate('news');
        $this->logChange($actor, 'news_article', $article, 'created');

        return $article;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateNews(User $actor, NewsArticle $article, array $data, ?UploadedFile $cover): NewsArticle
    {
        Gate::forUser($actor)->authorize('update', $article);

        $payload = Arr::only($data, ['title', 'slug', 'excerpt', 'body']);
        $oldCoverId = $article->cover_media_id;
        $newCover = $this->storeOptionalMedia($cover, $actor);

        if ($newCover !== null) {
            $payload['cover_media_id'] = $newCover->getKey();
        }

        $article->fill($payload)->save();
        $this->synchronizeMedia($newCover);
        $this->withdrawReplacedMedia($oldCoverId, $article->cover_media_id);

        $this->cache->invalidate('news');
        $this->logChange($actor, 'news_article', $article, 'updated');

        return $article->refresh();
    }

    public function publishNews(User $actor, NewsArticle $article): NewsArticle
    {
        Gate::forUser($actor)->authorize('publish', $article);

        if ($article->status === PublicationStatus::Published) {
            $this->synchronizeMedia($article->coverMedia);

            return $article;
        }

        $article->forceFill([
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'archived_at' => null,
        ])->save();
        $this->synchronizeMedia($article->coverMedia);
        $this->cache->invalidate('news');
        $this->logChange($actor, 'news_article', $article, 'published');

        return $article->refresh();
    }

    public function unpublishNews(User $actor, NewsArticle $article): NewsArticle
    {
        Gate::forUser($actor)->authorize('unpublish', $article);

        $article->forceFill([
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'archived_at' => null,
        ])->save();
        $this->synchronizeMedia($article->coverMedia);
        $this->cache->invalidate('news');
        $this->logChange($actor, 'news_article', $article, 'unpublished');

        return $article->refresh();
    }

    public function archiveNews(User $actor, NewsArticle $article): NewsArticle
    {
        Gate::forUser($actor)->authorize('archive', $article);

        $article->forceFill([
            'status' => PublicationStatus::Archived,
            'archived_at' => now(),
        ])->save();
        $this->synchronizeMedia($article->coverMedia);
        $this->cache->invalidate('news');
        $this->logChange($actor, 'news_article', $article, 'archived');

        return $article->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createAchievement(User $actor, array $data, ?UploadedFile $cover): Achievement
    {
        Gate::forUser($actor)->authorize('create', Achievement::class);

        $payload = Arr::only($data, [
            'title',
            'slug',
            'description',
            'category',
            'level',
            'achievement_date',
        ]);
        $media = $this->storeOptionalMedia($cover, $actor);
        $achievement = Achievement::query()->create([
            ...$payload,
            'cover_media_id' => $media?->getKey(),
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'archived_at' => null,
        ]);

        $this->cache->invalidate('achievements');
        $this->logChange($actor, 'achievement', $achievement, 'created');

        return $achievement;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAchievement(User $actor, Achievement $achievement, array $data, ?UploadedFile $cover): Achievement
    {
        Gate::forUser($actor)->authorize('update', $achievement);

        $payload = Arr::only($data, [
            'title',
            'slug',
            'description',
            'category',
            'level',
            'achievement_date',
        ]);
        $this->guardPublishedAchievementDate($achievement, $payload);
        $oldCoverId = $achievement->cover_media_id;
        $newCover = $this->storeOptionalMedia($cover, $actor);

        if ($newCover !== null) {
            $payload['cover_media_id'] = $newCover->getKey();
        }

        $achievement->fill($payload)->save();
        $this->synchronizeMedia($newCover);
        $this->withdrawReplacedMedia($oldCoverId, $achievement->cover_media_id);

        $this->cache->invalidate('achievements');
        $this->logChange($actor, 'achievement', $achievement, 'updated');

        return $achievement->refresh();
    }

    public function publishAchievement(User $actor, Achievement $achievement): Achievement
    {
        Gate::forUser($actor)->authorize('publish', $achievement);

        if ($achievement->achievement_date?->isAfter(today())) {
            throw ValidationException::withMessages([
                'achievement_date' => 'A future achievement cannot be published.',
            ]);
        }

        if ($achievement->status === PublicationStatus::Published) {
            $this->synchronizeMedia($achievement->coverMedia);

            return $achievement;
        }

        $achievement->forceFill([
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'archived_at' => null,
        ])->save();
        $this->synchronizeMedia($achievement->coverMedia);
        $this->cache->invalidate('achievements');
        $this->logChange($actor, 'achievement', $achievement, 'published');

        return $achievement->refresh();
    }

    public function unpublishAchievement(User $actor, Achievement $achievement): Achievement
    {
        Gate::forUser($actor)->authorize('unpublish', $achievement);

        $achievement->forceFill([
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'archived_at' => null,
        ])->save();
        $this->synchronizeMedia($achievement->coverMedia);
        $this->cache->invalidate('achievements');
        $this->logChange($actor, 'achievement', $achievement, 'unpublished');

        return $achievement->refresh();
    }

    public function archiveAchievement(User $actor, Achievement $achievement): Achievement
    {
        Gate::forUser($actor)->authorize('archive', $achievement);

        $achievement->forceFill([
            'status' => PublicationStatus::Archived,
            'archived_at' => now(),
        ])->save();
        $this->synchronizeMedia($achievement->coverMedia);
        $this->cache->invalidate('achievements');
        $this->logChange($actor, 'achievement', $achievement, 'archived');

        return $achievement->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createGalleryAlbum(User $actor, array $data): GalleryAlbum
    {
        Gate::forUser($actor)->authorize('create', GalleryAlbum::class);

        $album = GalleryAlbum::query()->create([
            ...Arr::only($data, ['title', 'slug', 'description', 'sort_order']),
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'archived_at' => null,
        ]);

        $this->cache->invalidate('gallery');
        $this->logChange($actor, 'gallery_album', $album, 'created');

        return $album;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateGalleryAlbum(User $actor, GalleryAlbum $album, array $data): GalleryAlbum
    {
        Gate::forUser($actor)->authorize('update', $album);

        $album->fill(Arr::only($data, ['title', 'slug', 'description', 'sort_order']))->save();
        $this->cache->invalidate('gallery');
        $this->logChange($actor, 'gallery_album', $album, 'updated');

        return $album->refresh();
    }

    public function publishGalleryAlbum(User $actor, GalleryAlbum $album): GalleryAlbum
    {
        Gate::forUser($actor)->authorize('publish', $album);

        if ($album->status === PublicationStatus::Published) {
            $this->synchronizePublishedAlbumItems($album);

            return $album;
        }

        $album->forceFill([
            'status' => PublicationStatus::Published,
            'published_at' => now(),
            'archived_at' => null,
        ])->save();
        $this->synchronizePublishedAlbumItems($album);
        $this->cache->invalidate('gallery');
        $this->logChange($actor, 'gallery_album', $album, 'published');

        return $album->refresh();
    }

    public function unpublishGalleryAlbum(User $actor, GalleryAlbum $album): GalleryAlbum
    {
        Gate::forUser($actor)->authorize('unpublish', $album);

        $album->forceFill([
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'archived_at' => null,
        ])->save();
        $this->synchronizePublishedAlbumItems($album);
        $this->cache->invalidate('gallery');
        $this->logChange($actor, 'gallery_album', $album, 'unpublished');

        return $album->refresh();
    }

    public function archiveGalleryAlbum(User $actor, GalleryAlbum $album): GalleryAlbum
    {
        Gate::forUser($actor)->authorize('archive', $album);

        $album->forceFill([
            'status' => PublicationStatus::Archived,
            'archived_at' => now(),
        ])->save();
        $this->synchronizePublishedAlbumItems($album);
        $this->cache->invalidate('gallery');
        $this->logChange($actor, 'gallery_album', $album, 'archived');

        return $album->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createGalleryItem(User $actor, GalleryAlbum $album, array $data, UploadedFile $image): GalleryItem
    {
        Gate::forUser($actor)->authorize('create', GalleryItem::class);
        $media = $this->mediaAssets->store($image, $actor);

        $item = GalleryItem::query()->create([
            ...Arr::only($data, ['caption', 'alt_text', 'event_date', 'sort_order']),
            'gallery_album_id' => $album->getKey(),
            'media_asset_id' => $media->getKey(),
            'status' => GalleryItemStatus::Draft,
            'published_at' => null,
            'archived_at' => null,
        ]);

        $this->cache->invalidate('gallery');
        $this->logChange($actor, 'gallery_item', $item, 'created');

        return $item;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateGalleryItem(
        User $actor,
        GalleryAlbum $album,
        GalleryItem $item,
        array $data,
        ?UploadedFile $image,
    ): GalleryItem {
        Gate::forUser($actor)->authorize('update', $item);

        if ((int) $item->gallery_album_id !== (int) $album->getKey()) {
            throw ValidationException::withMessages([
                'gallery_album_id' => 'The gallery item does not belong to this album.',
            ]);
        }

        $payload = Arr::only($data, ['caption', 'alt_text', 'event_date', 'sort_order']);
        $oldMediaId = $item->media_asset_id;
        $newMedia = $this->storeOptionalMedia($image, $actor);

        if ($newMedia !== null) {
            $payload['media_asset_id'] = $newMedia->getKey();
        }

        $item->fill($payload)->save();
        $this->synchronizeMedia($newMedia);
        $this->withdrawReplacedMedia($oldMediaId, $item->media_asset_id);

        $this->cache->invalidate('gallery');
        $this->logChange($actor, 'gallery_item', $item, 'updated');

        return $item->refresh();
    }

    public function publishGalleryItem(User $actor, GalleryItem $item): GalleryItem
    {
        Gate::forUser($actor)->authorize('publish', $item);

        if ($item->status === GalleryItemStatus::Published) {
            $this->synchronizeMedia($item->mediaAsset);

            return $item;
        }

        $item->forceFill([
            'status' => GalleryItemStatus::Published,
            'published_at' => now(),
            'archived_at' => null,
        ])->save();
        $this->synchronizeMedia($item->mediaAsset);
        $this->cache->invalidate('gallery');
        $this->logChange($actor, 'gallery_item', $item, 'published');

        return $item->refresh();
    }

    public function unpublishGalleryItem(User $actor, GalleryItem $item): GalleryItem
    {
        Gate::forUser($actor)->authorize('unpublish', $item);

        $item->forceFill([
            'status' => GalleryItemStatus::Draft,
            'published_at' => null,
            'archived_at' => null,
        ])->save();
        $this->synchronizeMedia($item->mediaAsset);
        $this->cache->invalidate('gallery');
        $this->logChange($actor, 'gallery_item', $item, 'unpublished');

        return $item->refresh();
    }

    public function hideGalleryItem(User $actor, GalleryItem $item): GalleryItem
    {
        Gate::forUser($actor)->authorize('hide', $item);

        $item->forceFill([
            'status' => GalleryItemStatus::Hidden,
            'published_at' => null,
            'archived_at' => null,
        ])->save();
        $this->synchronizeMedia($item->mediaAsset);
        $this->cache->invalidate('gallery');
        $this->logChange($actor, 'gallery_item', $item, 'hidden');

        return $item->refresh();
    }

    public function archiveGalleryItem(User $actor, GalleryItem $item): GalleryItem
    {
        Gate::forUser($actor)->authorize('archive', $item);

        $item->forceFill([
            'status' => GalleryItemStatus::Archived,
            'archived_at' => now(),
        ])->save();
        $this->synchronizeMedia($item->mediaAsset);
        $this->cache->invalidate('gallery');
        $this->logChange($actor, 'gallery_item', $item, 'archived');

        return $item->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTeacherPublicProfile(
        User $actor,
        Teacher $teacher,
        array $data,
        ?UploadedFile $photo,
    ): Teacher {
        Gate::forUser($actor)->authorize('managePublicProfile', $teacher);

        $payload = Arr::only($data, ['directory_slug', 'position_title', 'biography']);
        $oldPhotoId = $teacher->photo_media_id;
        $newPhoto = $this->storeOptionalMedia($photo, $actor);

        if ($this->flag($data, 'remove_photo')) {
            $payload['photo_media_id'] = null;
        }

        if ($newPhoto !== null) {
            $payload['photo_media_id'] = $newPhoto->getKey();
        }

        if (! $teacher->isActive()) {
            $payload['is_publicly_visible'] = false;
            $payload['published_at'] = null;
        }

        $teacher->fill($payload)->save();
        $this->synchronizeMedia($newPhoto);
        $this->withdrawReplacedMedia($oldPhotoId, $teacher->photo_media_id);

        $this->cache->invalidate('teachers');
        $this->logChange($actor, 'teacher_public_profile', $teacher, 'updated');

        return $teacher->refresh();
    }

    public function publishTeacher(User $actor, Teacher $teacher): Teacher
    {
        Gate::forUser($actor)->authorize('publishPublicProfile', $teacher);

        if (! $teacher->isActive()) {
            $teacher->forceFill([
                'is_publicly_visible' => false,
                'published_at' => null,
            ])->save();
            $this->synchronizeMedia($teacher->photoMedia);
            $this->cache->invalidate('teachers');
            $this->logChange($actor, 'teacher_public_profile', $teacher, 'withdrawn_inactive');

            throw ValidationException::withMessages([
                'teacher' => 'Only an active teacher can be published.',
            ]);
        }

        if ($teacher->directory_slug === null || $teacher->directory_slug === '') {
            throw ValidationException::withMessages([
                'directory_slug' => 'A public directory slug is required before publication.',
            ]);
        }

        $teacher->forceFill([
            'is_publicly_visible' => true,
            'published_at' => now(),
        ])->save();
        $this->synchronizeMedia($teacher->photoMedia);
        $this->cache->invalidate('teachers');
        $this->logChange($actor, 'teacher_public_profile', $teacher, 'published');

        return $teacher->refresh();
    }

    public function unpublishTeacher(User $actor, Teacher $teacher): Teacher
    {
        Gate::forUser($actor)->authorize('publishPublicProfile', $teacher);

        $teacher->forceFill([
            'is_publicly_visible' => false,
            'published_at' => null,
        ])->save();
        $this->synchronizeMedia($teacher->photoMedia);
        $this->cache->invalidate('teachers');
        $this->logChange($actor, 'teacher_public_profile', $teacher, 'unpublished');

        return $teacher->refresh();
    }

    private function storeOptionalMedia(?UploadedFile $file, User $actor): ?MediaAsset
    {
        return $file === null ? null : $this->mediaAssets->store($file, $actor);
    }

    /**
     * Read a removal checkbox that may arrive as a string, a boolean, or an absent value.
     *
     * @param  array<string, mixed>  $data
     */
    private function flag(array $data, string $key): bool
    {
        return filter_var($data[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Synchronize a replaced or removed media asset so its public derivative is withdrawn.
     */
    private function withdrawReplacedMedia(mixed $previousMediaId, mixed $currentMediaId): void
    {
        if ($previousMediaId === null) {
            return;
        }

        $previousId = (int) $previousMediaId;

        if ($currentMediaId !== null && (int) $currentMediaId === $previousId) {
            return;
        }

        $mediaAsset = MediaAsset::query()->find($previousId);

        if ($mediaAsset !== null) {
            $this->mediaAssets->synchronize($mediaAsset);
        }
    }

    /**
     * A published achievement must never advertise a date that has not happened yet.
     *
     * @param  array<string, mixed>  $payload
     */
    private function guardPublishedAchievementDate(Achievement $achievement, array $payload): void
    {
        if ($achievement->status !== PublicationStatus::Published) {
            return;
        }

        $achievementDate = $payload['achievement_date'] ?? null;

        if (! is_string($achievementDate) || ! Carbon::hasFormat($achievementDate, 'Y-m-d')) {
            return;
        }

        if (Carbon::parse($achievementDate)->startOfDay()->isAfter(today())) {
            throw ValidationException::withMessages([
                'achievement_date' => 'A published achievement cannot be moved to a future date.',
            ]);
        }
    }

    private function synchronizeMedia(?MediaAsset $media): void
    {
        if ($media !== null) {
            $this->cache->invalidate('school-profile', 'news', 'achievements', 'gallery', 'teachers');
            $this->mediaAssets->synchronize($media);
        }
    }

    private function synchronizePublishedAlbumItems(GalleryAlbum $album): void
    {
        GalleryItem::query()
            ->published()
            ->where('gallery_album_id', $album->getKey())
            ->with('mediaAsset')
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    $this->synchronizeMedia($item->mediaAsset);
                }
            });
    }

    private function logChange(User $actor, string $contentType, object $content, string $action): void
    {
        $this->audit->record(
            actor: $actor,
            action: 'content.'.$action,
            targetType: $contentType,
            targetId: $content->getKey(),
            context: ['content_type' => $contentType],
        );
    }
}
