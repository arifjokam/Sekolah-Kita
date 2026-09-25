<?php

namespace Database\Factories;

use App\Enums\AdmissionPeriodStatus;
use App\Models\AcademicYear;
use App\Models\AdmissionPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AdmissionPeriod>
 */
class AdmissionPeriodFactory extends Factory
{
    protected $model = AdmissionPeriod::class;

    public function definition(): array
    {
        $opensAt = now()->subDay();
        $closesAt = now()->addMonths(2);

        return [
            'academic_year_id' => AcademicYear::factory()->active(),
            'name' => 'PPDB Tahun Ajaran '.fake()->year(),
            'slug' => 'ppdb-'.Str::lower(Str::random(10)),
            'description' => fake()->paragraph(),
            'instructions' => fake()->sentences(3, true),
            'internal_notes' => null,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'status' => AdmissionPeriodStatus::Open,
            'quota' => fake()->numberBetween(30, 120),
            'published_at' => now()->subHour(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AdmissionPeriodStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AdmissionPeriodStatus::Closed,
            'opens_at' => now()->subMonths(3),
            'closes_at' => now()->subDay(),
        ]);
    }
}
