<?php

namespace App\Services;

use App\Enums\MediaAssetKind;
use App\Enums\MediaProcessingStatus;
use App\Enums\MediaScanStatus;
use App\Enums\MediaVisibility;
use App\Models\Achievement;
use App\Models\ApplicationDocument;
use App\Models\GalleryItem;
use App\Models\MediaAsset;
use App\Models\NewsArticle;
use App\Models\SchoolProfile;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MediaAssetService
{
    private const MAX_BYTES = 5_242_880;

    private const MAX_WIDTH = 6_000;

    private const MAX_HEIGHT = 6_000;

    private const MAX_PIXELS = 20_000_000;

    /**
     * Validate, decode, and re-encode an image before storing its sanitized source privately.
     *
     * @return array{binary: string, mime_type: string, extension: string, width: int, height: int}
     */
    public function store(UploadedFile $file, User $uploader): MediaAsset
    {
        $image = $this->sanitize($file);
        $objectKey = 'media/'.Str::uuid().'.'.$image['extension'];
        $privateDisk = Storage::disk('local');

        if (! $privateDisk->put($objectKey, $image['binary'])) {
            throw new RuntimeException('The sanitized media source could not be stored.');
        }

        try {
            return MediaAsset::query()->create([
                'uploader_user_id' => $uploader->getKey(),
                'media_kind' => MediaAssetKind::Image,
                'original_filename' => null,
                'private_disk' => 'local',
                'private_object_key' => $objectKey,
                'public_disk' => null,
                'public_object_key' => null,
                'mime_type' => $image['mime_type'],
                'size_bytes' => strlen($image['binary']),
                'checksum_sha256' => hash('sha256', $image['binary']),
                'width' => $image['width'],
                'height' => $image['height'],
                'processing_status' => MediaProcessingStatus::Ready,
                'scan_status' => MediaScanStatus::Clean,
                'scanned_at' => now(),
                'scan_error_code' => null,
                'visibility' => MediaVisibility::Private,
                'metadata_sanitized_at' => now(),
                'published_at' => null,
                'deleted_at' => null,
            ]);
        } catch (Throwable $exception) {
            $privateDisk->delete($objectKey);

            throw $exception;
        }
    }

    public function synchronize(MediaAsset $mediaAsset): void
    {
        if ($mediaAsset->processing_status === MediaProcessingStatus::Deleted) {
            return;
        }

        if ($this->hasPublicReference($mediaAsset)) {
            $this->publicize($mediaAsset);

            return;
        }

        $this->privatize($mediaAsset);
    }

    public function publicUrl(?MediaAsset $mediaAsset): ?string
    {
        if ($mediaAsset === null || ! $mediaAsset->isPublicReady()) {
            return null;
        }

        return Storage::disk($mediaAsset->public_disk)->url($mediaAsset->public_object_key);
    }

    public function discard(MediaAsset $mediaAsset): void
    {
        if ($this->hasAnyReference($mediaAsset)) {
            return;
        }

        if ($mediaAsset->public_disk !== null && $mediaAsset->public_object_key !== null) {
            Storage::disk($mediaAsset->public_disk)->delete($mediaAsset->public_object_key);
        }

        Storage::disk($mediaAsset->private_disk)->delete($mediaAsset->private_object_key);

        $mediaAsset->forceFill([
            'visibility' => MediaVisibility::Private,
            'public_disk' => null,
            'public_object_key' => null,
            'published_at' => null,
            'processing_status' => MediaProcessingStatus::Deleted,
            'deleted_at' => now(),
        ])->save();
    }

    private function publicize(MediaAsset $mediaAsset): void
    {
        if ($mediaAsset->media_kind !== MediaAssetKind::Image
            || $mediaAsset->processing_status !== MediaProcessingStatus::Ready
            || $mediaAsset->scan_status !== MediaScanStatus::Clean
            || $mediaAsset->metadata_sanitized_at === null) {
            throw new RuntimeException('Only sanitized images can be published.');
        }

        if ($mediaAsset->isPublicReady()) {
            return;
        }

        if ($mediaAsset->processing_status === MediaProcessingStatus::Deleted || $mediaAsset->deleted_at !== null) {
            throw new RuntimeException('A deleted media asset cannot be published.');
        }

        $source = Storage::disk($mediaAsset->private_disk)->get($mediaAsset->private_object_key);

        if ($source === null || $source === '') {
            throw new RuntimeException('The private media source is unavailable.');
        }

        $extension = $this->extensionForMimeType($mediaAsset->mime_type);
        $publicKey = 'media/'.Str::uuid().'.'.$extension;
        $publicDisk = Storage::disk('public');

        if (! $publicDisk->put($publicKey, $source, 'public')) {
            throw new RuntimeException('The public media derivative could not be stored.');
        }

        try {
            $mediaAsset->forceFill([
                'visibility' => MediaVisibility::Public,
                'public_disk' => 'public',
                'public_object_key' => $publicKey,
                'published_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $publicDisk->delete($publicKey);

            throw $exception;
        }
    }

    private function privatize(MediaAsset $mediaAsset): void
    {
        if ($mediaAsset->visibility !== MediaVisibility::Public
            && $mediaAsset->public_disk === null
            && $mediaAsset->public_object_key === null) {
            return;
        }

        if ($mediaAsset->public_disk !== null && $mediaAsset->public_object_key !== null) {
            $deleted = Storage::disk($mediaAsset->public_disk)->delete($mediaAsset->public_object_key);

            if (! $deleted) {
                throw new RuntimeException('The public media derivative could not be withdrawn.');
            }
        }

        $mediaAsset->forceFill([
            'visibility' => MediaVisibility::Private,
            'public_disk' => null,
            'public_object_key' => null,
            'published_at' => null,
        ])->save();
    }

    private function hasPublicReference(MediaAsset $mediaAsset): bool
    {
        $mediaId = $mediaAsset->getKey();

        return SchoolProfile::query()
            ->where('logo_media_id', $mediaId)
            ->orWhere('hero_media_id', $mediaId)
            ->exists()
            || NewsArticle::query()->published()->where('cover_media_id', $mediaId)->exists()
            || Achievement::query()->published()->where('cover_media_id', $mediaId)->exists()
            || Teacher::query()->publiclyVisible()->where('photo_media_id', $mediaId)->exists()
            || GalleryItem::query()
                ->published()
                ->where('media_asset_id', $mediaId)
                ->whereHas('galleryAlbum', fn ($query) => $query->published())
                ->exists();
    }

    private function hasAnyReference(MediaAsset $mediaAsset): bool
    {
        $mediaId = $mediaAsset->getKey();

        return SchoolProfile::query()
            ->where('logo_media_id', $mediaId)
            ->orWhere('hero_media_id', $mediaId)
            ->exists()
            || NewsArticle::query()->where('cover_media_id', $mediaId)->exists()
            || Achievement::query()->where('cover_media_id', $mediaId)->exists()
            || Teacher::query()->where('photo_media_id', $mediaId)->exists()
            || GalleryItem::query()->where('media_asset_id', $mediaId)->exists()
            || ApplicationDocument::query()->where('media_asset_id', $mediaId)->exists();
    }

    /**
     * @return array{binary: string, mime_type: string, extension: string, width: int, height: int}
     */
    private function sanitize(UploadedFile $file): array
    {
        $size = $file->getSize();

        if ($file->getError() !== UPLOAD_ERR_OK || $size === false || $size > self::MAX_BYTES) {
            throw new RuntimeException('The uploaded image is invalid or too large.');
        }

        $path = $file->getRealPath();
        $imageInfo = $path === false ? false : @getimagesize($path);

        if ($imageInfo === false) {
            throw new RuntimeException('The uploaded file is not a supported image.');
        }

        [$width, $height] = $imageInfo;
        $mimeType = $imageInfo['mime'] ?? null;

        if (! is_string($mimeType) || ! array_key_exists($mimeType, $this->allowedMimeTypes())) {
            throw new RuntimeException('The uploaded image type is not allowed.');
        }

        if ($width < 1 || $height < 1 || $width > self::MAX_WIDTH || $height > self::MAX_HEIGHT || ($width * $height) > self::MAX_PIXELS) {
            throw new RuntimeException('The uploaded image dimensions are not allowed.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('The uploaded image could not be read.');
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('The uploaded image could not be decoded.');
        }

        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            imagedestroy($source);

            throw new RuntimeException('The sanitized image buffer could not be created.');
        }

        if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
            imagealphablending($canvas, true);
        } else {
            $background = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $background);
        }

        imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $width,
            $height,
        );

        ob_start();

        $encoded = match ($mimeType) {
            'image/jpeg' => imagejpeg($canvas, null, 90),
            'image/png' => imagepng($canvas, null, 9),
            'image/webp' => function_exists('imagewebp') ? imagewebp($canvas, null, 85) : false,
            default => false,
        };

        $binary = ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);

        if (! $encoded || ! is_string($binary) || $binary === '') {
            throw new RuntimeException('The image could not be sanitized.');
        }

        return [
            'binary' => $binary,
            'mime_type' => $mimeType,
            'extension' => $this->extensionForMimeType($mimeType),
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function allowedMimeTypes(): array
    {
        return [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
    }

    private function extensionForMimeType(string $mimeType): string
    {
        return $this->allowedMimeTypes()[$mimeType] ?? throw new RuntimeException('Unsupported media MIME type.');
    }
}
