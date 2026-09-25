<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'slug',
    'description',
    'status',
    'published_at',
    'archived_at',
    'sort_order',
])]
class GalleryAlbum extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => PublicationStatus::class,
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(GalleryItem::class);
    }

    public function galleryItems(): HasMany
    {
        return $this->items();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PublicationStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
