<?php

namespace App\Models;

use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionConversionStatus;
use App\Enums\AdmissionDocumentVerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'admission_period_id',
    'applicant_user_id',
    'applicant_name',
    'preferred_name',
    'national_id',
    'nisn',
    'birth_date',
    'birth_place',
    'gender',
    'contact_email',
    'contact_phone',
    'contact_whatsapp',
    'address',
    'address_rt',
    'address_rw',
    'village',
    'district',
    'regency',
    'province',
    'postal_code',
    'current_school',
    'graduation_year',
    'requested_grade_level',
    'source',
    'consent_version',
    'consent_accepted_at',
    'contact_verified_at',
    'submitted_at',
    'locked_at',
    'target_class_id',
])]
#[Hidden([
    'registration_number',
    'applicant_user_id',
    'applicant_name',
    'preferred_name',
    'national_id',
    'nisn',
    'birth_date',
    'birth_place',
    'gender',
    'contact_email',
    'contact_phone',
    'contact_whatsapp',
    'address',
    'address_rt',
    'address_rw',
    'village',
    'district',
    'regency',
    'province',
    'postal_code',
    'current_school',
    'graduation_year',
    'duplicate_fingerprint',
    'assigned_reviewer_user_id',
    'target_class_id',
    'converted_student_id',
    'converted_enrollment_id',
    'conversion_status',
    'conversion_error_code',
    'contact_verified_at',
    'duplicate_flag',
    'duplicate_reviewed_at',
    'workflow_version',
    'consent_version',
])]
class AdmissionApplication extends Model
{
    use HasFactory;

    protected $casts = [
        'status' => AdmissionApplicationStatus::class,
        'conversion_status' => AdmissionConversionStatus::class,
        'birth_date' => 'date',
        'requested_grade_level' => 'integer',
        'graduation_year' => 'integer',
        'consent_accepted_at' => 'datetime',
        'contact_verified_at' => 'datetime',
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
        'revision_requested_at' => 'datetime',
        'revision_deadline' => 'datetime',
        'decided_at' => 'datetime',
        'accepted_at' => 'datetime',
        'converted_at' => 'datetime',
        'duplicate_reviewed_at' => 'datetime',
        'workflow_version' => 'integer',
        'duplicate_flag' => 'boolean',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(AdmissionPeriod::class, 'admission_period_id');
    }

    /**
     * The portal account that owns this application, when it came from the
     * applicant portal rather than the public one-shot form.
     */
    public function applicantUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_reviewer_user_id');
    }

    public function targetClass(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'target_class_id');
    }

    public function convertedStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'converted_student_id');
    }

    public function convertedEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'converted_enrollment_id');
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(ApplicationGuardian::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AdmissionReview::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class);
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(AdmissionApplicationAccessToken::class);
    }

    public function scopeForReviewer(Builder $query, User $reviewer): Builder
    {
        return $query->where('assigned_reviewer_user_id', $reviewer->getKey());
    }

    public function isAccepted(): bool
    {
        return $this->status === AdmissionApplicationStatus::Accepted;
    }

    public function canRevise(): bool
    {
        return $this->status === AdmissionApplicationStatus::RevisionRequested
            && ($this->revision_deadline === null || $this->revision_deadline->greaterThanOrEqualTo(now()));
    }

    public function publicStatusLabel(): string
    {
        return $this->status->label();
    }

    /*
    |--------------------------------------------------------------------------
    | Applicant portal state
    |--------------------------------------------------------------------------
    */

    /**
     * Whether this application is owned by a PPDB portal account.
     */
    public function isPortalApplication(): bool
    {
        return $this->applicant_user_id !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === AdmissionApplicationStatus::Draft;
    }

    public function isSubmitted(): bool
    {
        return $this->status === AdmissionApplicationStatus::Submitted;
    }

    /**
     * Whether the applicant may still change the biodata and guardian sections.
     *
     * A draft and an application awaiting a requested revision are both
     * editable. Everything else is either under review, decided, or withdrawn:
     * a change at that point would invalidate a decision the school already made.
     */
    public function isPortalEditable(): bool
    {
        if ($this->isDraft()) {
            return true;
        }

        return $this->canRevise();
    }

    /**
     * Whether any document is currently sitting in "needs revision".
     *
     * This is what makes a document-level correction a single-document fix: the
     * application can be under review, with every other document already accepted,
     * and the candidate can still replace only the one document a reviewer sent
     * back — without the whole application returning to a revise-and-resubmit
     * cycle.
     */
    public function hasOpenDocumentRevision(): bool
    {
        return $this->documents()
            ->where('verification_status', AdmissionDocumentVerificationStatus::NeedsRevision->value)
            ->exists();
    }

    /**
     * Whether a new document version may be uploaded.
     */
    public function canAcceptDocument(): bool
    {
        return $this->isDraft()
            || $this->canRevise()
            || $this->hasOpenDocumentRevision();
    }
}
