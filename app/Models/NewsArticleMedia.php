<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'news_article_id',
    'media_asset_id',
])]
class NewsArticleMedia extends Model
{
    use HasFactory;

    /**
     * A staged row has no article yet: the admin uploaded the image on the
     * create screen before the article was saved.
     */
    public function isStaged(): bool
    {
        return $this->news_article_id === null;
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(NewsArticle::class, 'news_article_id');
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_asset_id');
    }
}
