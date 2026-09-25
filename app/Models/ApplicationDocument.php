<?php

namespace App\Models;

use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionDocumentVerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'admission_application_id',
    'media_asset_id',
    'document_type',
    'version',
    'valid_from',
    'valid_until',
    'verification_status',
    'reviewer_note',
    'reviewed_by_user_id',
    'reviewed_at',
])]
#[Hidden(['reviewer_note'])]
class ApplicationDocument extends Model
{
    use HasFactory;

    protected $casts = [
        'document_type' => AdmissionDocumentType::class,
        'verification_status' => AdmissionDocumentVerificationStatus::class,
        'version' => 'integer',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
