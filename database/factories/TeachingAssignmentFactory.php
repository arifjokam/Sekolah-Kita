<?php

namespace Database\Factories;

use App\Enums\AssignmentStatus;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeachingAssignment;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeachingAssignment>
 */
class TeachingAssignmentFactory extends Factory
{
    protected $model = TeachingAssignment::class;

    public function definition(): array
    {
        return [
            'teacher_id' => Teacher::factory(),
            'class_id' => ClassModel::factory(),
            'subject_id' => Subject::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'term_id' => null,
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

    public function forTerm(Term $term): static
    {
        return $this->state(fn (array $attributes): array => [
            'term_id' => $term->id,
            'academic_year_id' => $term->academic_year_id,
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
