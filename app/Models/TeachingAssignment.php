<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'teacher_id',
    'class_id',
    'subject_id',
    'academic_year_id',
    'term_id',
    'status',
    'starts_on',
    'ends_on',
    'internal_reason',
])]
class TeachingAssignment extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => AssignmentStatus::class,
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        $today = today();

        return $query
            ->where('status', AssignmentStatus::Active->value)
            ->where('starts_on', '<=', $today)
            ->where(function (Builder $query) use ($today): void {
                $query->whereNull('ends_on')
                    ->orWhere('ends_on', '>=', $today);
            });
    }

    public function isActive(): bool
    {
        $today = today();

        return $this->status === AssignmentStatus::Active
            && $this->starts_on !== null
            && $this->starts_on <= $today
            && ($this->ends_on === null || $this->ends_on >= $today);
    }
}
