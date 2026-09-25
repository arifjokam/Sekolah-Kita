<?php

namespace Database\Factories;

use App\Enums\AdmissionDocumentType;
use App\Enums\AdmissionDocumentVerificationStatus;
use App\Models\AdmissionApplication;
use App\Models\ApplicationDocument;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    protected $model = ApplicationDocument::class;

    public function definition(): array
    {
        return [
            'admission_application_id' => AdmissionApplication::factory(),
            'media_asset_id' => MediaAsset::factory(),
            'document_type' => AdmissionDocumentType::Identity,
            'version' => 1,
            'verification_status' => AdmissionDocumentVerificationStatus::Pending,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_status' => AdmissionDocumentVerificationStatus::Verified,
            'reviewed_at' => now(),
        ]);
    }
}
