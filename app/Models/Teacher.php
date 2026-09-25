<?php

namespace App\Models;

use App\Enums\TeacherEmploymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'legal_name',
    'preferred_name',
    'employee_number',
    'employment_status',
    'employment_start_date',
    'employment_end_date',
    'position_title',
    'qualification_summary',
    'biography',
    'birth_date',
    'birth_place',
    'gender',
    'national_id',
    'address',
    'phone',
    'email',
])]
class Teacher extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'employee_number',
        'national_id',
        'address',
        'phone',
        'email',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'employment_status' => TeacherEmploymentStatus::class,
        'employment_start_date' => 'date',
        'employment_end_date' => 'date',
        'birth_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function classTeacherAssignments(): HasMany
    {
        return $this->hasMany(ClassTeacherAssignment::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function activeClassTeacherAssignments(): HasMany
    {
        return $this->classTeacherAssignments()->active();
    }

    public function activeTeachingAssignments(): HasMany
    {
        return $this->teachingAssignments()->active();
    }

    public function getDisplayName(): string
    {
        return $this->preferred_name ?? $this->legal_name;
    }

    public function isActive(): bool
    {
        return $this->employment_status === TeacherEmploymentStatus::Active;
    }
}
