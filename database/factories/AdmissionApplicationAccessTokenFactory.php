<?php

namespace Database\Factories;

use App\Enums\AdmissionAccessTokenPurpose;
use App\Models\AdmissionApplication;
use App\Models\AdmissionApplicationAccessToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AdmissionApplicationAccessToken>
 */
class AdmissionApplicationAccessTokenFactory extends Factory
{
    protected $model = AdmissionApplicationAccessToken::class;

    public function definition(): array
    {
        return [
            'admission_application_id' => AdmissionApplication::factory(),
            'token_hash' => hash('sha256', (string) Str::random(64)),
            'purpose' => AdmissionAccessTokenPurpose::Status,
            'expires_at' => now()->addDays(7),
        ];
    }
}
