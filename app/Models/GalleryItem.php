<?php

namespace App\Models;

use App\Enums\GalleryItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'gallery_album_id',
    'media_asset_id',
    'caption',
    'alt_text',
    'event_date',
    'status',
    'published_at',
    'archived_at',
    'sort_order',
])]
class GalleryItem extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'event_date' => 'date',
        'status' => GalleryItemStatus::class,
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function galleryAlbum(): BelongsTo
    {
        return $this->belongsTo(GalleryAlbum::class);
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', GalleryItemStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
