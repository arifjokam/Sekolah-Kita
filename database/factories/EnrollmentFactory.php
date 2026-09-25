<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'class_id' => ClassModel::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'status' => EnrollmentStatus::Pending,
            'enrolled_on' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'effective_from' => null,
            'effective_to' => null,
            'completed_on' => null,
            'transition_reason' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EnrollmentStatus::Pending,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EnrollmentStatus::Active,
            'effective_from' => now()->subMonths(3)->format('Y-m-d'),
        ]);
    }

    public function transferred(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EnrollmentStatus::Transferred,
            'effective_from' => now()->subMonths(4)->format('Y-m-d'),
            'effective_to' => now()->subMonth()->format('Y-m-d'),
            'transition_reason' => 'Pindah kelas',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EnrollmentStatus::Completed,
            'effective_from' => now()->subYear()->format('Y-m-d'),
            'effective_to' => now()->subMonth()->format('Y-m-d'),
            'completed_on' => now()->subMonth()->format('Y-m-d'),
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EnrollmentStatus::Withdrawn,
            'effective_from' => now()->subMonths(6)->format('Y-m-d'),
            'effective_to' => now()->subMonths(3)->format('Y-m-d'),
            'transition_reason' => 'Keluar dari sekolah',
        ]);
    }

    public function onLeave(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EnrollmentStatus::OnLeave,
            'effective_from' => now()->subMonths(6)->format('Y-m-d'),
            'transition_reason' => 'Cuti akademik',
        ]);
    }

    public function forStudent(Student $student): static
    {
        return $this->state(fn (array $attributes): array => [
            'student_id' => $student->id,
        ]);
    }

    public function forClass(ClassModel $class): static
    {
        return $this->state(fn (array $attributes): array => [
            'class_id' => $class->id,
        ]);
    }
}
