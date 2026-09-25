<?php

namespace Database\Factories;

use App\Enums\MediaAssetKind;
use App\Enums\MediaProcessingStatus;
use App\Enums\MediaScanStatus;
use App\Enums\MediaVisibility;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    protected $model = MediaAsset::class;

    public function definition(): array
    {
        $objectId = (string) Str::uuid();

        return [
            'uploader_user_id' => null,
            'media_kind' => MediaAssetKind::Image,
            'original_filename' => null,
            'private_disk' => 'local',
            'private_object_key' => 'media/'.$objectId.'.jpg',
            'public_disk' => null,
            'public_object_key' => null,
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(10_000, 500_000),
            'checksum_sha256' => hash('sha256', $objectId),
            'width' => fake()->numberBetween(800, 1600),
            'height' => fake()->numberBetween(600, 1200),
            'processing_status' => MediaProcessingStatus::Ready,
            'scan_status' => MediaScanStatus::Clean,
            'scanned_at' => now(),
            'scan_error_code' => null,
            'visibility' => MediaVisibility::Private,
            'metadata_sanitized_at' => now(),
            'published_at' => null,
            'deleted_at' => null,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes): array => [
            'public_disk' => 'public',
            'public_object_key' => 'media/'.Str::uuid().'.jpg',
            'visibility' => MediaVisibility::Public,
            'published_at' => now()->subMinute(),
        ]);
    }
}
