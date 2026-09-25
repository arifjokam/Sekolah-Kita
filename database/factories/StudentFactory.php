<?php

namespace Database\Factories;

use App\Enums\StudentLifecycleStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'legal_name' => fake()->name(),
            'preferred_name' => fake()->optional()->firstName(),
            'student_number' => 'NIS-'.strtoupper(Str::random(8)),
            'status' => StudentLifecycleStatus::Active,
            'admission_date' => fake()->dateTimeBetween('-6 years', 'now'),
            'graduation_date' => null,
            'leaving_date' => null,
            'leaving_reason' => null,
            'birth_date' => fake()->dateTimeBetween('-18 years', '-6 years'),
            'birth_place' => fake()->city(),
            'gender' => fake()->randomElement(['L', 'P']),
            'national_id' => fake()->optional()->numerify('################'),
            'nisn' => fake()->optional()->numerify('##########'),
            'address' => fake()->address(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StudentLifecycleStatus::Active,
        ]);
    }

    public function onLeave(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StudentLifecycleStatus::OnLeave,
        ]);
    }

    public function graduated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StudentLifecycleStatus::Graduated,
            'graduation_date' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    public function transferred(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StudentLifecycleStatus::Transferred,
            'leaving_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'leaving_reason' => 'Mutasi ke sekolah lain',
        ]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StudentLifecycleStatus::Withdrawn,
            'leaving_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'leaving_reason' => 'Keluar dari sekolah',
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => StudentLifecycleStatus::Archived,
        ]);
    }

    public function withUser(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => User::factory()->create()->id,
        ]);
    }
}
