<?php

namespace Database\Factories;

use App\Enums\AdmissionActorType;
use App\Enums\AdmissionApplicationStatus;
use App\Models\AdmissionApplication;
use App\Models\ApplicationStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApplicationStatusHistory>
 */
class ApplicationStatusHistoryFactory extends Factory
{
    protected $model = ApplicationStatusHistory::class;

    public function definition(): array
    {
        return [
            'admission_application_id' => AdmissionApplication::factory(),
            'from_status' => AdmissionApplicationStatus::Submitted,
            'to_status' => AdmissionApplicationStatus::UnderReview,
            'actor_type' => AdmissionActorType::Staff,
            'reason_code' => 'review_started',
            'public_message' => 'Pengajuan sedang ditinjau.',
            'correlation_id' => (string) Str::uuid(),
            'workflow_version' => 2,
            'occurred_at' => now(),
        ];
    }
}
