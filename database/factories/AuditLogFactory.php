<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory(),
            'action' => 'test.action',
            'result' => 'success',
            'target_type' => 'test_resource',
            'target_id' => (string) fake()->numberBetween(1, 999999),
            'changed_fields' => ['status'],
            'reason_code' => null,
            'correlation_id' => (string) Str::uuid(),
            'ip_hash' => hash('sha256', 'test-ip'),
            'user_agent_hash' => hash('sha256', 'test-agent'),
            'occurred_at' => now(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'result' => 'failed',
        ]);
    }

    public function denied(): static
    {
        return $this->state(fn (array $attributes): array => [
            'result' => 'denied',
        ]);
    }
}
