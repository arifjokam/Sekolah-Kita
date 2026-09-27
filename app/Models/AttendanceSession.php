<?php

namespace App\Models;

use App\Enums\AttendanceSessionStatus;
use App\Enums\WeekDay;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The concrete occurrence of a scheduled lesson on a date.
 *
 * This is the record a teacher actually marks attendance against, and the unit a
 * monthly report aggregates over. It snapshots the academic scope and the
 * subject rather than reading them through the schedule, so editing a schedule
 * later cannot silently rewrite history.
 */
#[Fillable([
    'schedule_id',
    'academic_year_id',
    'term_id',
    'class_id',
    'subject_id',
    'teacher_id',
    'session_date',
    'starts_at',
    'ends_at',
    'status',
    'note',
    'marked_by_user_id',
    'submitted_at',
])]
class AttendanceSession extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'session_date' => 'date',
        'status' => AttendanceSessionStatus::class,
        'submitted_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ClassSchedule::class, 'schedule_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by_user_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function scopeOnDate(Builder $query, Carbon|string $date): Builder
    {
        return $query->where('session_date', $date instanceof Carbon ? $date->toDateString() : $date);
    }

    public function scopeInMonth(Builder $query, int $year, int $month): Builder
    {
        return $query
            ->whereYear('session_date', $year)
            ->whereMonth('session_date', $month);
    }

    public function dayOfWeek(): WeekDay
    {
        return WeekDay::from($this->session_date->dayOfWeekIso);
    }

    public function isSubmitted(): bool
    {
        return $this->status !== AttendanceSessionStatus::Open;
    }

    /**
     * Whether the session still accepts marks.
     *
     * A submitted session is corrected through the dedicated correction path
     * rather than by silently reopening the form, so a correction is always an
     * explicit, audited action.
     */
    public function isEditable(): bool
    {
        return $this->status === AttendanceSessionStatus::Open;
    }
}
