<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\ClassModel;
use App\Models\ClassTeacherAssignment;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassTeacherAssignment>
 */
class ClassTeacherAssignmentFactory extends Factory
{
    protected $model = ClassTeacherAssignment::class;

    public function definition(): array
    {
        return [
            'class_id' => ClassModel::factory(),
            'teacher_id' => Teacher::factory(),
            'status' => AssignmentStatus::Planned,
            'starts_on' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'ends_on' => null,
            'internal_reason' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AssignmentStatus::Active,
            'starts_on' => now()->subMonths(3)->format('Y-m-d'),
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AssignmentStatus::Ended,
            'ends_on' => now()->subMonth()->format('Y-m-d'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AssignmentStatus::Cancelled,
            'internal_reason' => 'Pengajuan cuti/diperlukan di tempat lain',
        ]);
    }

    public function forClass(ClassModel $class): static
    {
        return $this->state(fn (array $attributes): array => [
            'class_id' => $class->id,
        ]);
    }

    public function forTeacher(Teacher $teacher): static
    {
        return $this->state(fn (array $attributes): array => [
            'teacher_id' => $teacher->id,
        ]);
    }
}
