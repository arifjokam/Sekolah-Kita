<?php

namespace App\Models;

use App\Enums\ClassStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'academic_year_id',
    'grade_level',
    'name',
    'capacity',
    'status',
    'closed_at',
    'closure_reason',
])]
class ClassModel extends Model
{
    use HasFactory;

    protected $table = 'classes';

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ClassStatus::class,
        'closed_at' => 'date',
        'capacity' => 'integer',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function enrollments(): HasMany
    {
        // The foreign key is named explicitly because the class basename
        // (ClassModel) does not produce the "class_id" column Laravel would
        // otherwise infer.
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    public function classTeacherAssignments(): HasMany
    {
        return $this->hasMany(ClassTeacherAssignment::class, 'class_id');
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'class_id');
    }

    public function activeEnrollments(): HasMany
    {
        return $this->enrollments()->active();
    }

    public function currentHomeroomTeacher(): ?ClassTeacherAssignment
    {
        return $this->classTeacherAssignments()
            ->active()
            ->with('teacher')
            ->first();
    }

    public function isActive(): bool
    {
        return $this->status === ClassStatus::Active;
    }

    public function getFullName(): string
    {
        return "{$this->grade_level} {$this->name}";
    }
}
