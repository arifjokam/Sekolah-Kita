<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\Achievement;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Achievement>
 */
class AchievementFactory extends Factory
{
    protected $model = Achievement::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'cover_media_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraph(),
            'category' => fake()->randomElement(['Akademik', 'Olimpiade', 'Seni', 'Olahraga']),
            'level' => fake()->randomElement(['Sekolah', 'Kabupaten', 'Provinsi', 'Nasional']),
            'achievement_date' => fake()->dateTimeBetween('-2 years', 'now')->format('Y-m-d'),
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'archived_at' => null,
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
