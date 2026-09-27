<?php

namespace App\Models;

use App\Enums\ScheduleStatus;
use App\Enums\WeekDay;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One recurring weekly lesson.
 *
 * Deliberately named ClassSchedule rather than "Schedule": the latter collides
 * with Illuminate\Console\Scheduling\Schedule, which this project already uses
 * through routes/console.php.
 */
#[Fillable([
    'academic_year_id',
    'term_id',
    'class_id',
    'subject_id',
    'teacher_id',
    'day_of_week',
    'starts_at',
    'ends_at',
    'room',
    'status',
])]
class ClassSchedule extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'day_of_week' => WeekDay::class,
        'status' => ScheduleStatus::class,
    ];

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
        return $this->belongsTo(ClassModel::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'schedule_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ScheduleStatus::Active->value);
    }

    public function scopeOnDay(Builder $query, WeekDay|int $day): Builder
    {
        $value = $day instanceof WeekDay ? $day->value : $day;

        return $query->where('day_of_week', $value);
    }

    public function scopeOnDate(Builder $query, Carbon $date): Builder
    {
        return $query->where('day_of_week', $date->dayOfWeekIso);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('day_of_week')->orderBy('starts_at');
    }

    public function isActive(): bool
    {
        return $this->status === ScheduleStatus::Active;
    }

    public function timeRange(): string
    {
        return substr((string) $this->starts_at, 0, 5).'–'.substr((string) $this->ends_at, 0, 5);
    }

    public function durationMinutes(): int
    {
        $start = strtotime((string) $this->starts_at);
        $end = strtotime((string) $this->ends_at);

        if ($start === false || $end === false || $end <= $start) {
            return 0;
        }

        return (int) round(($end - $start) / 60);
    }

    /**
     * Whether this lesson falls on the given calendar date.
     */
    public function occursOn(Carbon $date): bool
    {
        return $this->day_of_week->value === $date->dayOfWeekIso;
    }
}
