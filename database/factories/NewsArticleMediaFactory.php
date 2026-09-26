<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use App\Models\NewsArticle;
use App\Models\NewsArticleMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsArticleMedia>
 */
class NewsArticleMediaFactory extends Factory
{
    protected $model = NewsArticleMedia::class;

    public function definition(): array
    {
        return [
            'news_article_id' => NewsArticle::factory(),
            'media_asset_id' => MediaAsset::factory(),
        ];
    }

    public function staged(): static
    {
        return $this->state(fn (array $attributes): array => [
            'news_article_id' => null,
        ]);
    }
}
