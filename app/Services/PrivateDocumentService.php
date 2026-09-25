<?php

namespace App\Services;

use App\Enums\MediaAssetKind;
use App\Enums\MediaProcessingStatus;
use App\Enums\MediaScanStatus;
use App\Enums\MediaVisibility;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PrivateDocumentService
{
    /**
     * @var array<string, string>
     */
    private const ALLOWED_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    private const MAX_IMAGE_WIDTH = 6000;

    private const MAX_IMAGE_HEIGHT = 6000;

    private const MAX_IMAGE_PIXELS = 20_000_000;

    public function store(UploadedFile $file, ?User $uploader): MediaAsset
    {
        $this->assertUploadIsSafe($file);
        $extension = self::ALLOWED_TYPES[$file->getMimeType()];
        $objectKey = 'admission-documents/'.Str::uuid().'.'.$extension;
        $disk = Storage::disk('local');
        $sourceBinary = file_get_contents($file->getRealPath());

        if ($sourceBinary === false) {
            throw new RuntimeException('The private admission document could not be read.');
        }

        $sanitized = $this->sanitizeBinary($file->getMimeType(), $sourceBinary);
        $binary = $sanitized['binary'];
        $maxBytes = (int) config('sekolah-kita.ppdb.max_document_bytes', 10_485_760);

        if (strlen($binary) > $maxBytes || ! $disk->put($objectKey, $binary)) {
            throw new RuntimeException('The private admission document could not be stored.');
        }

        try {
            return MediaAsset::query()->create([
                'uploader_user_id' => $uploader?->getKey(),
                'media_kind' => MediaAssetKind::Document,
                'original_filename' => $this->safeOriginalFilename($file->getClientOriginalName()),
                'private_disk' => 'local',
                'private_object_key' => $objectKey,
                'public_disk' => null,
                'public_object_key' => null,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => strlen($binary),
                'checksum_sha256' => hash('sha256', $binary),
                'width' => $sanitized['width'],
                'height' => $sanitized['height'],
                'processing_status' => MediaProcessingStatus::Quarantined,
                'scan_status' => MediaScanStatus::Pending,
                'scanned_at' => null,
                'scan_error_code' => null,
                'visibility' => MediaVisibility::Private,
                'metadata_sanitized_at' => $sanitized['metadata_sanitized'] ? now() : null,
                'published_at' => null,
                'deleted_at' => null,
            ]);
        } catch (Throwable $exception) {
            $disk->delete($objectKey);

            throw $exception;
        }
    }

    public function inspect(MediaAsset $mediaAsset): bool
    {
        if ($mediaAsset->media_kind !== MediaAssetKind::Document) {
            return false;
        }

        $disk = Storage::disk($mediaAsset->private_disk);
        $binary = $disk->get($mediaAsset->private_object_key);

        if (! is_string($binary) || $binary === '') {
            $this->markFailed($mediaAsset, 'missing_object');

            return false;
        }

        if (hash('sha256', $binary) !== $mediaAsset->checksum_sha256 || strlen($binary) !== $mediaAsset->size_bytes) {
            $this->markFailed($mediaAsset, 'checksum_mismatch');

            return false;
        }

        $isSafe = $this->contentIsSafe($mediaAsset->mime_type, $binary);

        if (! $isSafe) {
            $this->markFailed($mediaAsset, 'unsafe_content');

            return false;
        }

        $mediaAsset->forceFill([
            'processing_status' => MediaProcessingStatus::Ready,
            'scan_status' => MediaScanStatus::Clean,
            'scanned_at' => now(),
            'scan_error_code' => null,
        ])->save();

        return true;
    }

    public function markFailed(MediaAsset $mediaAsset, string $errorCode): void
    {
        $mediaAsset->forceFill([
            'processing_status' => MediaProcessingStatus::Failed,
            'scan_status' => MediaScanStatus::Failed,
            'scan_error_code' => Str::limit($errorCode, 80, ''),
        ])->save();
    }

    private function assertUploadIsSafe(UploadedFile $file): void
    {
        $maxBytes = (int) config('sekolah-kita.ppdb.max_document_bytes', 10_485_760);
        $size = $file->getSize();
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());

        if ($file->getError() !== UPLOAD_ERR_OK || $size === false || $size < 1 || $size > $maxBytes) {
            throw new RuntimeException('The admission document is invalid or too large.');
        }

        if (! isset(self::ALLOWED_TYPES[$mimeType]) || ! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
            throw new RuntimeException('The admission document type is not allowed.');
        }

        $path = $file->getRealPath();
        $binary = $path === false ? false : file_get_contents($path);

        if ($binary === false || ! $this->contentIsSafe($mimeType, $binary)) {
            throw new RuntimeException('The admission document content is not allowed.');
        }
    }

    private function contentIsSafe(string $mimeType, string $binary): bool
    {
        if ($mimeType === 'application/pdf') {
            return str_starts_with($binary, '%PDF-')
                && preg_match('/\/(?:JavaScript|JS|OpenAction|AA|Launch|EmbeddedFile|URI)\b/i', $binary) !== 1;
        }

        $imageInfo = @getimagesizefromstring($binary);

        if ($imageInfo === false) {
            return false;
        }

        $detectedMime = $imageInfo['mime'] ?? null;

        return $detectedMime === $mimeType
            && $this->imageDimensionsAreSafe($imageInfo);
    }

    /**
     * @return array{binary: string, width: int|null, height: int|null, metadata_sanitized: bool}
     */
    private function sanitizeBinary(string $mimeType, string $binary): array
    {
        if ($mimeType === 'application/pdf') {
            return [
                'binary' => $binary,
                'width' => null,
                'height' => null,
                'metadata_sanitized' => false,
            ];
        }

        $imageInfo = @getimagesizefromstring($binary);
        if ($imageInfo === false || ($imageInfo['mime'] ?? null) !== $mimeType || ! $this->imageDimensionsAreSafe($imageInfo)) {
            throw new RuntimeException('The admission document image is invalid.');
        }

        [$width, $height] = [(int) $imageInfo[0], (int) $imageInfo[1]];
        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            throw new RuntimeException('The admission document image could not be decoded.');
        }

        $canvas = imagecreatetruecolor($width, $height);
        if ($canvas === false) {
            imagedestroy($source);

            throw new RuntimeException('The admission document image buffer could not be created.');
        }

        if ($mimeType === 'image/png') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
            imagealphablending($canvas, true);
        } else {
            $background = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $width, $height, $background);
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $width, $height);
        ob_start();
        $encoded = match ($mimeType) {
            'image/jpeg' => imagejpeg($canvas, null, 90),
            'image/png' => imagepng($canvas, null, 9),
            default => false,
        };
        $sanitizedBinary = ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);

        if (! $encoded || ! is_string($sanitizedBinary) || $sanitizedBinary === '') {
            throw new RuntimeException('The admission document image could not be sanitized.');
        }

        return [
            'binary' => $sanitizedBinary,
            'width' => $width,
            'height' => $height,
            'metadata_sanitized' => true,
        ];
    }

    /**
     * @param  array<int|string, mixed>  $imageInfo
     */
    private function imageDimensionsAreSafe(array $imageInfo): bool
    {
        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);

        return $width > 0
            && $height > 0
            && $width <= self::MAX_IMAGE_WIDTH
            && $height <= self::MAX_IMAGE_HEIGHT
            && ($width * $height) <= self::MAX_IMAGE_PIXELS;
    }

    private function safeOriginalFilename(string $filename): string
    {
        $basename = basename($filename);

        return Str::limit(preg_replace('/[^A-Za-z0-9._-]/', '_', $basename) ?: 'document', 180, '');
    }
}
