<?php

namespace App\Models;

use App\Enums\AdmissionReviewDecision;
use App\Enums\AdmissionReviewEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'admission_application_id',
    'application_document_id',
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
        'event_type' => AdmissionReviewEventType::class,
        'decision' => AdmissionReviewDecision::class,
        'reviewed_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'admission_application_id');
    }

    /**
     * Set for a document-level review event, null for the application-level
     * events (assignment, decision, revision request) that already existed.
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(ApplicationDocument::class, 'application_document_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    /**
     * A human label for what happened.
     *
     * An unknown stored value is returned verbatim rather than discarded: this is
     * a review record, and a row whose event predates the closed vocabulary still
     * has to be readable by the administrator who has to act on it.
     */
    public function eventLabel(): string
    {
        return $this->event_type instanceof AdmissionReviewEventType
            ? $this->event_type->label()
            : AdmissionReviewEventType::describe(
                is_string($this->event_type) ? $this->event_type : (string) $this->getRawOriginal('event_type'),
            );
    }

    /**
     * Whether this review concerns a single document rather than the application.
     */
    public function isDocumentLevel(): bool
    {
        return $this->application_document_id !== null;
    }

    /**
     * The note attached to a document-level review.
     *
     * internal_note is hidden from array and JSON output on purpose — it is the
     * reviewer's private remark — but for a document sent back, the same text is
     * also the reason shown to the applicant, so it is exposed here as a
     * deliberately narrower accessor rather than by widening the model's surface.
     */
    public function applicantVisibleNote(): ?string
    {
        return $this->event_type instanceof AdmissionReviewEventType
            && $this->event_type === AdmissionReviewEventType::DocumentRevisionRequested
                ? $this->internal_note
                : null;
    }
}
