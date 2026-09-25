<?php

namespace Database\Factories;

use App\Enums\AdmissionReviewDecision;
use App\Models\AdmissionApplication;
use App\Models\AdmissionReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionReview>
 */
class AdmissionReviewFactory extends Factory
{
    protected $model = AdmissionReview::class;

    public function definition(): array
    {
        return [
            'admission_application_id' => AdmissionApplication::factory(),
            'reviewer_user_id' => User::factory()->administrator(),
            'event_type' => 'decision',
            'decision' => AdmissionReviewDecision::Accept,
            'internal_note' => fake()->sentence(),
            'reviewed_at' => now(),
        ];
    }
}
