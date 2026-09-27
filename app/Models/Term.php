<?php

namespace App\Models;

use App\Enums\TermStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'academic_year_id',
    'sequence',
    'label',
    'starts_on',
    'ends_on',
    'status',
])]
class Term extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => TermStatus::class,
        'sequence' => 'integer',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'term_id');
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'term_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TermStatus::Active->value);
    }

    /**
     * The terms of one academic year, in semester order.
     *
     * Semester 1 and Semester 2 are two rows of the same year, so "the terms of a
     * year" is an ordinary hasMany and not a single-value relation.
     */
    public function scopeForAcademicYear(Builder $query, int $academicYearId): Builder
    {
        return $query->where('academic_year_id', $academicYearId)->orderBy('sequence');
    }

    public function isActive(): bool
    {
        return $this->status === TermStatus::Active;
    }

    /**
     * The user-facing name.
     *
     * Indonesian schools say "Semester", not "Term". The class, the table and the
     * route names stay `Term` because renaming them would add migration and
     * authorisation risk for no user benefit; only the display copy changes.
     */
    public function semesterLabel(): string
    {
        $label = trim((string) $this->label);

        if (str_contains(mb_strtolower($label), 'semester')) {
            return $label;
        }

        return 'Semester '.$this->sequence;
    }
}
