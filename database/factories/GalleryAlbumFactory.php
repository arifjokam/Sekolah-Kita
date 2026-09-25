<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\GalleryAlbum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GalleryAlbum>
 */
class GalleryAlbumFactory extends Factory
{
    protected $model = GalleryAlbum::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraph(),
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'archived_at' => null,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PublicationStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }
}
