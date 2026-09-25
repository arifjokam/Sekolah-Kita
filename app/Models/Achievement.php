<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cover_media_id',
    'title',
    'slug',
    'description',
    'category',
    'level',
    'achievement_date',
    'status',
    'published_at',
    'archived_at',
])]
class Achievement extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'achievement_date' => 'date',
        'status' => PublicationStatus::class,
        'published_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'cover_media_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PublicationStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
