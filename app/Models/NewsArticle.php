<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'author_user_id',
    'cover_media_id',
    'title',
    'slug',
    'excerpt',
    'body',
    'status',
    'published_at',
    'archived_at',
])]
class NewsArticle extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => PublicationStatus::class,
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    /**
     * Media assets embedded in the article body, as opposed to the cover image.
     *
     * @return HasMany<NewsArticleMedia, $this>
     */
    public function bodyMedia(): HasMany
    {
        return $this->hasMany(NewsArticleMedia::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PublicationStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
