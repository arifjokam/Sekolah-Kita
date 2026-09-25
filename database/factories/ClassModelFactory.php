<?php

namespace Database\Factories;

use App\Enums\ClassStatus;
use App\Models\AcademicYear;
use App\Models\ClassModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassModel>
 */
class ClassModelFactory extends Factory
{
    protected $model = ClassModel::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'grade_level' => fake()->numberBetween(1, 12),
            'name' => fake()->randomElement(['A', 'B', 'C', 'D', 'E', 'F']),
            'capacity' => fake()->numberBetween(25, 40),
            'status' => ClassStatus::Planned,
            'closed_at' => null,
            'closure_reason' => null,
        ];
    }

    public function grade(int $grade): static
    {
        return $this->state(fn (array $attributes): array => [
            'grade_level' => $grade,
        ]);
    }

    public function planned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ClassStatus::Planned,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ClassStatus::Active,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ClassStatus::Closed,
            'closed_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'closure_reason' => 'Akhir tahun ajaran',
        ]);
    }

    public function withCapacity(int $capacity): static
    {
        return $this->state(fn (array $attributes): array => [
            'capacity' => $capacity,
        ]);
    }

    public function name(string $name): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $name,
        ]);
    }
}
