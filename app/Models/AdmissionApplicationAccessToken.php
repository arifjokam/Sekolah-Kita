<?php

namespace App\Models;

use App\Enums\AdmissionAccessTokenPurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'admission_application_id',
    'token_hash',
    'purpose',
    'expires_at',
    'consumed_at',
    'revoked_at',
    'last_used_at',
])]
#[Hidden(['token_hash'])]
class AdmissionApplicationAccessToken extends Model
{
    use HasFactory;

    protected $casts = [
        'purpose' => AdmissionAccessTokenPurpose::class,
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function isUsable(): bool
    {
        return $this->expires_at->greaterThan(now())
            && $this->consumed_at === null
            && $this->revoked_at === null;
    }
}
