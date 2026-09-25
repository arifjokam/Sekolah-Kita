<?php

namespace Database\Factories;

use App\Models\SchoolProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolProfile>
 */
class SchoolProfileFactory extends Factory
{
    protected $model = SchoolProfile::class;

    public function definition(): array
    {
        return [
            'is_singleton' => true,
            'legal_name' => 'Sekolah Kita Nusantara',
            'public_name' => 'Sekolah Kita',
            'short_name' => 'SKN',
            'address_line' => fake()->streetAddress(),
            'city' => fake()->city(),
            'postal_code' => fake()->postcode(),
            'public_phone' => fake()->numerify('021-#######'),
            'public_email' => fake()->companyEmail(),
            'vision' => fake()->sentence(),
            'mission' => fake()->paragraph(),
            'about' => fake()->paragraphs(2, true),
            'logo_media_id' => null,
            'hero_media_id' => null,
        ];
    }
}
