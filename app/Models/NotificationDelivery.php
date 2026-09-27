<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The durable record of one out-of-band notification attempt.
 *
 * A row exists for every attempt, including the ones that could not happen.
 * That is the whole point: with no WhatsApp provider configured the row says
 * NotConfigured, so the portal and the reviewer screens can state honestly that
 * the message was not sent instead of implying success.
 */
#[Fillable([
    'channel',
    'provider',
    'notification_class',
    'notifiable_type',
    'notifiable_id',
    'recipient',
    'status',
    'attempts',
    'body',
    'last_error',
    'sent_at',
])]
class NotificationDelivery extends Model
{
    use HasFactory;

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'channel' => NotificationChannel::class,
        'status' => NotificationDeliveryStatus::class,
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isDelivered(): bool
    {
        return $this->status === NotificationDeliveryStatus::Sent;
    }

    public function isNotConfigured(): bool
    {
        return $this->status === NotificationDeliveryStatus::NotConfigured;
    }
}
