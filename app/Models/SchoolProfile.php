<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'is_singleton',
    'legal_name',
    'public_name',
    'short_name',
    'address_line',
    'city',
    'postal_code',
    'public_phone',
    'public_email',
    'vision',
    'mission',
    'about',
    'logo_media_id',
    'hero_media_id',
])]
class SchoolProfile extends Model
{
    use HasFactory;

    protected $casts = [
        'is_singleton' => 'boolean',
    ];

    public function logoMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_id');
    }

    public function heroMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'hero_media_id');
    }

    public function displayName(): string
    {
        return $this->public_name ?: $this->legal_name;
    }
}
