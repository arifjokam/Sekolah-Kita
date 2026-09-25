<?php

namespace Database\Factories;

use App\Enums\AdmissionApplicationStatus;
use App\Enums\AdmissionConversionStatus;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AdmissionApplication>
 */
class AdmissionApplicationFactory extends Factory
{
    protected $model = AdmissionApplication::class;

    public function definition(): array
    {
        return [
            'admission_period_id' => AdmissionPeriod::factory(),
            'registration_number' => strtoupper(Str::random(16)),
            'applicant_name' => fake()->name(),
            'preferred_name' => fake()->optional()->firstName(),
            'birth_date' => fake()->dateTimeBetween('-18 years', '-6 years')->format('Y-m-d'),
            'birth_place' => fake()->city(),
            'gender' => fake()->randomElement(['L', 'P']),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->numerify('08##########'),
            'address' => fake()->address(),
            'current_school' => fake()->optional()->company(),
            'requested_grade_level' => fake()->numberBetween(1, 12),
            'source' => 'public_web',
            'status' => AdmissionApplicationStatus::Submitted,
            'workflow_version' => 1,
            'conversion_status' => AdmissionConversionStatus::NotStarted,
            'consent_version' => config('sekolah-kita.ppdb.consent_version', '2026-01'),
            'consent_accepted_at' => now(),
            'submitted_at' => now(),
            'duplicate_flag' => false,
        ];
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AdmissionApplicationStatus::UnderReview,
        ]);
    }

    public function revisionRequested(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AdmissionApplicationStatus::RevisionRequested,
            'revision_requested_at' => now(),
            'revision_deadline' => now()->addDays(7),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AdmissionApplicationStatus::Accepted,
            'decided_at' => now(),
            'accepted_at' => now(),
            'conversion_status' => AdmissionConversionStatus::NotStarted,
        ]);
    }
}
