<?php

namespace Database\Factories;

use App\Enums\GalleryItemStatus;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryItem>
 */
class GalleryItemFactory extends Factory
{
    protected $model = GalleryItem::class;

    public function definition(): array
    {
        return [
            'gallery_album_id' => GalleryAlbum::factory(),
            'media_asset_id' => MediaAsset::factory()->public(),
            'caption' => fake()->optional()->sentence(),
            'alt_text' => fake()->sentence(8),
            'event_date' => fake()->optional()->dateTimeBetween('-2 years', 'now')?->format('Y-m-d'),
            'status' => GalleryItemStatus::Draft,
            'published_at' => null,
            'archived_at' => null,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => GalleryItemStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }
}
