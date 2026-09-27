<?php

namespace Database\Factories;

use App\Enums\AttendanceSessionStatus;
use App\Models\AcademicYear;
use App\Models\AttendanceSession;
use App\Models\ClassModel;
use App\Models\ClassSchedule;
use App\Models\Teacher;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    protected $model = AttendanceSession::class;

    public function definition(): array
    {
        $schedule = ClassSchedule::factory();

        return [
            'schedule_id' => null,
            'academic_year_id' => AcademicYear::factory(),
            'term_id' => Term::factory(),
            'class_id' => ClassModel::factory(),
            'subject_id' => null,
            'teacher_id' => Teacher::factory(),
            'session_date' => today()->toDateString(),
            'starts_at' => '07:00',
            'ends_at' => '08:30',
            'status' => AttendanceSessionStatus::Open,
            'note' => null,
            'marked_by_user_id' => null,
            'submitted_at' => null,
        ];
    }

    public function forSchedule(ClassSchedule $schedule, ?Carbon $date = null): static
    {
        return $this->state(fn (): array => [
            'schedule_id' => $schedule->getKey(),
            'academic_year_id' => $schedule->academic_year_id,
            'term_id' => $schedule->term_id,
            'class_id' => $schedule->class_id,
            'subject_id' => $schedule->subject_id,
            'teacher_id' => $schedule->teacher_id,
            'session_date' => ($date ?? today())->toDateString(),
            'starts_at' => substr((string) $schedule->starts_at, 0, 5),
            'ends_at' => substr((string) $schedule->ends_at, 0, 5),
        ]);
    }

    public function onDate(Carbon $date): static
    {
        return $this->state(fn (): array => ['session_date' => $date->toDateString()]);
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => AttendanceSessionStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }
}
