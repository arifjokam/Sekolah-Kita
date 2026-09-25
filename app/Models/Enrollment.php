<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_id',
    'class_id',
    'academic_year_id',
    'status',
    'enrolled_on',
    'effective_from',
    'effective_to',
    'completed_on',
    'transition_reason',
])]
class Enrollment extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => EnrollmentStatus::class,
        'enrolled_on' => 'date',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'completed_on' => 'date',
    ];

    public function scopeActive(Builder $query): Builder
    {
        $today = today();

        return $query
            ->where('status', EnrollmentStatus::Active->value)
            ->where('effective_from', '<=', $today)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $today);
            });
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function isActive(): bool
    {
        $today = today();

        return $this->status === EnrollmentStatus::Active
            && $this->effective_from !== null
            && $this->effective_from <= $today
            && ($this->effective_to === null || $this->effective_to >= $today);
    }

    public function isCurrent(): bool
    {
        return $this->isActive();
    }
}
