<?php

namespace App\Models;

use App\Enums\MediaAssetKind;
use App\Enums\MediaProcessingStatus;
use App\Enums\MediaScanStatus;
use App\Enums\MediaVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'uploader_user_id',
    'media_kind',
    'original_filename',
    'private_disk',
    'private_object_key',
    'public_disk',
    'public_object_key',
    'mime_type',
    'size_bytes',
    'checksum_sha256',
    'width',
    'height',
    'processing_status',
    'scan_status',
    'scanned_at',
    'scan_error_code',
    'visibility',
    'metadata_sanitized_at',
    'published_at',
    'deleted_at',
])]
#[Hidden([
    'uploader_user_id',
    'private_disk',
    'private_object_key',
    'public_disk',
    'public_object_key',
    'checksum_sha256',
    'original_filename',
    'scan_error_code',
])]
class MediaAsset extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'media_kind' => MediaAssetKind::class,
        'processing_status' => MediaProcessingStatus::class,
        'scan_status' => MediaScanStatus::class,
        'visibility' => MediaVisibility::class,
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'metadata_sanitized_at' => 'datetime',
        'published_at' => 'datetime',
        'deleted_at' => 'datetime',
        'scanned_at' => 'datetime',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_user_id');
    }

    public function newsArticles(): HasMany
    {
        return $this->hasMany(NewsArticle::class, 'cover_media_id');
    }

    /**
     * @return HasMany<NewsArticleMedia, $this>
     */
    public function newsBodyUsages(): HasMany
    {
        return $this->hasMany(NewsArticleMedia::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class, 'cover_media_id');
    }

    public function galleryItems(): HasMany
    {
        return $this->hasMany(GalleryItem::class);
    }

    public function applicationDocuments(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function scopePublicReady(Builder $query): Builder
    {
        return $query
            ->where('media_kind', MediaAssetKind::Image->value)
            ->where('processing_status', MediaProcessingStatus::Ready->value)
            ->where('scan_status', MediaScanStatus::Clean->value)
            ->where('visibility', MediaVisibility::Public->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('public_disk', 'public')
            ->whereNotNull('public_object_key')
            ->whereNotNull('metadata_sanitized_at')
            ->whereNull('deleted_at');
    }

    public function isPublicReady(): bool
    {
        return $this->media_kind === MediaAssetKind::Image
            && $this->processing_status === MediaProcessingStatus::Ready
            && $this->scan_status === MediaScanStatus::Clean
            && $this->visibility === MediaVisibility::Public
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(now())
            && $this->public_disk === 'public'
            && $this->public_object_key !== null
            && $this->metadata_sanitized_at !== null
            && $this->deleted_at === null;
    }
}
