<?php

namespace Database\Factories;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $year = fake()->unique()->numberBetween(1990, 2100);

        return [
            'code' => $year.'/'.($year + 1),
            'label' => 'Tahun Ajaran '.$year.'/'.($year + 1),
            'starts_on' => fake()->dateTimeBetween('-2 years', '+1 year')->format('Y-07-01'),
            'ends_on' => fake()->dateTimeBetween('+1 year', '+3 years')->format('Y-06-30'),
            'status' => AcademicYearStatus::Draft,
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AcademicYearStatus::Draft,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AcademicYearStatus::Active,
            'starts_on' => now()->subMonths(3)->format('Y-m-d'),
            'ends_on' => now()->addMonths(9)->format('Y-m-d'),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AcademicYearStatus::Closed,
            'starts_on' => now()->subYears(2)->format('Y-07-01'),
            'ends_on' => now()->subYear()->format('Y-06-30'),
        ]);
    }
}
