<?php

namespace Database\Factories;

use App\Enums\TeacherEmploymentStatus;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        return [
            'legal_name' => fake()->name(),
            'preferred_name' => fake()->optional()->firstName(),
            'employee_number' => 'NIP-'.strtoupper(Str::random(8)),
            'employment_status' => TeacherEmploymentStatus::Active,
            'employment_start_date' => fake()->dateTimeBetween('-10 years', 'now'),
            'employment_end_date' => null,
            'position_title' => fake()->optional()->jobTitle(),
            'qualification_summary' => fake()->optional()->sentence(),
            'biography' => fake()->optional()->paragraph(),
            'photo_media_id' => null,
            'directory_slug' => null,
            'is_publicly_visible' => false,
            'published_at' => null,
            'birth_date' => fake()->optional()->dateTimeBetween('-60 years', '-22 years'),
            'birth_place' => fake()->optional()->city(),
            'gender' => fake()->optional()->randomElement(['L', 'P']),
            'national_id' => fake()->optional()->numerify('################'),
            'address' => fake()->optional()->address(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'employment_status' => TeacherEmploymentStatus::Active,
        ]);
    }

    public function onLeave(): static
    {
        return $this->state(fn (array $attributes): array => [
            'employment_status' => TeacherEmploymentStatus::Leave,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'employment_status' => TeacherEmploymentStatus::Inactive,
        ]);
    }

    public function retired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'employment_status' => TeacherEmploymentStatus::Retired,
            'employment_end_date' => fake()->dateTimeBetween('-5 years', 'now'),
        ]);
    }

    public function publiclyVisible(): static
    {
        return $this->state(fn (array $attributes): array => [
            'directory_slug' => Str::slug((string) $attributes['legal_name']).'-'.Str::lower(Str::random(6)),
            'is_publicly_visible' => true,
            'published_at' => now()->subDay(),
            'employment_status' => TeacherEmploymentStatus::Active,
        ]);
    }

    public function withUser(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => User::factory()->guru()->create()->id,
        ]);
    }
}
