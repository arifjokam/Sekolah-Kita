<?php

namespace App\Models;

use App\Enums\TeacherEmploymentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
    'photo_media_id',
    'directory_slug',
    'is_publicly_visible',
    'published_at',
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
        'birth_date',
        'birth_place',
        'gender',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'employment_status' => TeacherEmploymentStatus::class,
        'employment_start_date' => 'date',
        'employment_end_date' => 'date',
        'birth_date' => 'date',
        'is_publicly_visible' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photoMedia(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'photo_media_id');
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

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('is_publicly_visible', true)
            ->where('employment_status', TeacherEmploymentStatus::Active->value)
            ->whereNotNull('directory_slug')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
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
