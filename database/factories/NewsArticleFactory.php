<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsArticle>
 */
class NewsArticleFactory extends Factory
{
    protected $model = NewsArticle::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(6);

        return [
            'author_user_id' => User::factory()->administrator(),
            'cover_media_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'excerpt' => fake()->sentence(20),
            'body' => fake()->paragraphs(3, true),
            'status' => PublicationStatus::Draft,
            'published_at' => null,
            'archived_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PublicationStatus::Published,
            'published_at' => now()->subMinutes(fake()->numberBetween(1, 1_440)),
        ]);
    }
}
