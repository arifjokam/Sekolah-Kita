<?php

namespace App\Models;

use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionConversionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'admission_period_id',
    'applicant_name',
    'preferred_name',
    'birth_date',
    'birth_place',
    'gender',
    'contact_email',
    'contact_phone',
    'address',
    'current_school',
    'requested_grade_level',
    'source',
    'consent_version',
    'consent_accepted_at',
    'contact_verified_at',
    'submitted_at',
    'target_class_id',
])]
#[Hidden([
    'registration_number',
    'applicant_name',
    'preferred_name',
    'birth_date',
    'birth_place',
    'gender',
    'contact_email',
    'contact_phone',
    'address',
    'current_school',
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
        'consent_accepted_at' => 'datetime',
        'contact_verified_at' => 'datetime',
        'submitted_at' => 'datetime',
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
}
