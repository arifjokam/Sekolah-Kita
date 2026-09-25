<?php

namespace Database\Factories;

use App\Enums\GuardianRelationshipType;
use App\Models\AdmissionApplication;
use App\Models\ApplicationGuardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationGuardian>
 */
class ApplicationGuardianFactory extends Factory
{
    protected $model = ApplicationGuardian::class;

    public function definition(): array
    {
        return [
            'admission_application_id' => AdmissionApplication::factory(),
            'legal_name' => fake()->name(),
            'relationship_type' => GuardianRelationshipType::Father,
            'phone' => fake()->numerify('08##########'),
            'email' => fake()->safeEmail(),
            'address' => fake()->address(),
            'is_primary_contact' => true,
            'is_emergency_contact' => true,
            'priority_order' => 1,
        ];
    }
}
