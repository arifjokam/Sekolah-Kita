<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make(Str::password(32)),
            'remember_token' => Str::random(60),
            'role' => UserRole::Siswa,
            'status' => AccountStatus::Active,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AccountStatus::Inactive,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AccountStatus::Suspended,
        ]);
    }

    public function administrator(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Administrator,
        ]);
    }

    public function guru(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => UserRole::Guru,
        ]);
    }
}
