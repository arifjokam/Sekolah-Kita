<?php

namespace App\Contracts;

use App\Enums\NotificationDeliveryStatus;

/**
 * The outcome of one WhatsApp send attempt.
 *
 * A value object rather than an exception because "the provider is not
 * configured" and "the gateway returned 503" are normal, expected outcomes that
 * must be recorded on the delivery row rather than thrown past the caller.
 */
final class WhatsAppDeliveryResult
{
    public function __construct(
        public readonly NotificationDeliveryStatus $status,
        public readonly string $provider,
        public readonly ?string $error = null,
        public readonly ?string $externalId = null,
    ) {}

    public static function sent(string $provider, ?string $externalId = null): self
    {
        return new self(NotificationDeliveryStatus::Sent, $provider, null, $externalId);
    }

    public static function notConfigured(string $provider, string $reason): self
    {
        return new self(NotificationDeliveryStatus::NotConfigured, $provider, $reason);
    }

    public static function failed(string $provider, string $error): self
    {
        return new self(NotificationDeliveryStatus::Failed, $provider, $error);
    }

    public function isSent(): bool
    {
        return $this->status === NotificationDeliveryStatus::Sent;
    }

    public function isNotConfigured(): bool
    {
        return $this->status === NotificationDeliveryStatus::NotConfigured;
    }
}
