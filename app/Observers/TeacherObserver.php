<?php

namespace App\Observers;

use App\Models\MediaAsset;
use App\Models\Teacher;
use App\Services\MediaAssetService;
use App\Services\PublicContentCache;

/**
 * Keeps the public teacher directory and its photo derivatives consistent even when a
 * teacher record is changed outside the administrator publication workflow.
 */
class TeacherObserver
{
    /**
     * @var list<string>
     */
    private const PUBLIC_MEDIA_ATTRIBUTES = [
        'photo_media_id',
        'is_publicly_visible',
        'employment_status',
        'published_at',
    ];

    public function __construct(
        private readonly MediaAssetService $mediaAssets,
        private readonly PublicContentCache $cache,
    ) {}

    public function saved(Teacher $teacher): void
    {
        if (! $teacher->wasChanged(self::PUBLIC_MEDIA_ATTRIBUTES)) {
            return;
        }

        $this->refreshPublicMedia($teacher->photo_media_id, $teacher->getOriginal('photo_media_id'));
    }

    public function deleted(Teacher $teacher): void
    {
        $this->refreshPublicMedia(null, $teacher->photo_media_id);
    }

    /**
     * Withdraw the public derivative of any photo the teacher no longer exposes.
     */
    private function refreshPublicMedia(mixed $currentMediaId, mixed $previousMediaId): void
    {
        $this->cache->invalidate('teachers');

        $mediaIds = array_unique(array_filter(
            [$currentMediaId, $previousMediaId],
            static fn (mixed $mediaId): bool => $mediaId !== null,
        ));

        foreach ($mediaIds as $mediaId) {
            $mediaAsset = MediaAsset::query()->find((int) $mediaId);

            if ($mediaAsset !== null) {
                $this->mediaAssets->synchronize($mediaAsset);
            }
        }
    }
}
