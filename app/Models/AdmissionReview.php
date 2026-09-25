<?php

namespace App\Models;

use App\Enums\AdmissionReviewDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'admission_application_id',
    'reviewer_user_id',
    'event_type',
    'decision',
    'internal_note',
    'reviewed_at',
])]
#[Hidden(['internal_note'])]
class AdmissionReview extends Model
{
    use HasFactory;

    protected $casts = [
        'decision' => AdmissionReviewDecision::class,
        'reviewed_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }
}
