<?php

namespace Database\Factories;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'channel' => NotificationChannel::InApp,
            'category' => NotificationCategory::System,
            'enabled' => true,
        ];
    }
}
