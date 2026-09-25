<?php

namespace Database\Factories;

use App\Enums\TermStatus;
use App\Models\AcademicYear;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    protected $model = Term::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'sequence' => 1,
            'label' => 'Semester 1',
            'starts_on' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'ends_on' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'status' => TermStatus::Draft,
        ];
    }

    public function semester1(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sequence' => 1,
            'label' => 'Semester 1',
        ]);
    }

    public function semester2(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sequence' => 2,
            'label' => 'Semester 2',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TermStatus::Draft,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TermStatus::Active,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TermStatus::Closed,
        ]);
    }
}
