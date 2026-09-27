<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\CandidatePortalNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationDelivery>
 */
class NotificationDeliveryFactory extends Factory
{
    protected $model = NotificationDelivery::class;

    public function definition(): array
    {
        return [
            'channel' => NotificationChannel::WhatsApp,
            'provider' => 'fake',
            'notification_class' => CandidatePortalNotification::class,
            'notifiable_type' => (new User)->getMorphClass(),
            'notifiable_id' => User::factory(),
            'recipient' => '6281234567890',
            'status' => NotificationDeliveryStatus::Sent,
            'attempts' => 1,
            'body' => 'Pesan uji.',
            'last_error' => null,
            'sent_at' => now(),
        ];
    }

    public function notConfigured(string $reason = 'FONNTE_TOKEN belum diisi.'): static
    {
        return $this->state(fn (): array => [
            'status' => NotificationDeliveryStatus::NotConfigured,
            'sent_at' => null,
            'last_error' => $reason,
        ]);
    }

    public function failed(string $error = 'Gateway menolak.'): static
    {
        return $this->state(fn (): array => [
            'status' => NotificationDeliveryStatus::Failed,
            'sent_at' => null,
            'last_error' => $error,
        ]);
    }
}
