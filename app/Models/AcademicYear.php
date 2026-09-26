<?php

namespace App\Models;

use App\Enums\AcademicYearStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'label',
    'starts_on',
    'ends_on',
    'status',
    'description',
])]
class AcademicYear extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => AcademicYearStatus::class,
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function terms(): HasMany
    {
        return $this->hasMany(Term::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassModel::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function admissionPeriods(): HasMany
    {
        return $this->hasMany(AdmissionPeriod::class);
    }

    public function classTeacherAssignments(): HasMany
    {
        return $this->hasMany(ClassTeacherAssignment::class);
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class);
    }

    public function classSchedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class, 'academic_year_id');
    }

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class, 'academic_year_id');
    }

    public function isActive(): bool
    {
        return $this->status === AcademicYearStatus::Active;
    }

    public static function getCurrent(): ?self
    {
        $today = today();

        return self::where('status', AcademicYearStatus::Active->value)
            ->where('starts_on', '<=', $today)
            ->where('ends_on', '>=', $today)
            ->first();
    }
}
