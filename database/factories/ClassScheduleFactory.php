<?php

namespace Database\Factories;

use App\Enums\ScheduleStatus;
use App\Enums\WeekDay;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\ClassSchedule;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSchedule>
 */
class ClassScheduleFactory extends Factory
{
    protected $model = ClassSchedule::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'term_id' => Term::factory(),
            'class_id' => ClassModel::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => Teacher::factory(),
            'day_of_week' => WeekDay::Monday->value,
            'starts_at' => '07:00',
            'ends_at' => '08:30',
            'room' => 'R. 101',
            'status' => ScheduleStatus::Active,
        ];
    }

    public function monday(): static
    {
        return $this->state(fn (): array => ['day_of_week' => WeekDay::Monday->value]);
    }

    public function tuesday(): static
    {
        return $this->state(fn (): array => ['day_of_week' => WeekDay::Tuesday->value]);
    }

    public function at(string $start, string $end): static
    {
        return $this->state(fn (): array => ['starts_at' => $start, 'ends_at' => $end]);
    }

    public function inRoom(?string $room): static
    {
        return $this->state(fn (): array => ['room' => $room]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => ScheduleStatus::Active]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => ScheduleStatus::Inactive]);
    }
}
