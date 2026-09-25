<?php

namespace App\Models;

use App\Enums\StudentLifecycleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'legal_name',
    'preferred_name',
    'student_number',
    'status',
    'admission_date',
    'graduation_date',
    'leaving_date',
    'leaving_reason',
    'birth_date',
    'birth_place',
    'gender',
    'national_id',
    'nisn',
    'address',
    'phone',
    'email',
])]
class Student extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'student_number',
        'national_id',
        'nisn',
        'address',
        'phone',
        'email',
        'leaving_reason',
        'birth_date',
        'birth_place',
        'gender',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => StudentLifecycleStatus::class,
        'admission_date' => 'date',
        'graduation_date' => 'date',
        'leaving_date' => 'date',
        'birth_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'student_guardians')
            ->withPivot([
                'relationship_type',
                'is_primary_contact',
                'is_emergency_contact',
                'priority_order',
                'starts_on',
                'ends_on',
                'relationship_note',
            ])
            ->withTimestamps();
    }

    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->active();
    }

    public function convertedAdmissionApplications(): HasMany
    {
        return $this->hasMany(AdmissionApplication::class, 'converted_student_id');
    }

    public function currentEnrollment(): ?Enrollment
    {
        return $this->activeEnrollments()
            ->with(['class.academicYear'])
            ->first();
    }

    public function currentClass(): ?ClassModel
    {
        $enrollment = $this->currentEnrollment();

        return $enrollment?->class;
    }

    public function getDisplayName(): string
    {
        return $this->preferred_name ?? $this->legal_name;
    }

    public function isActive(): bool
    {
        return $this->status === StudentLifecycleStatus::Active;
    }
}
