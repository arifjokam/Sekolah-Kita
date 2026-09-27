<?php

namespace App\Services\Messaging;

use App\Contracts\WhatsAppDeliveryResult;
use App\Contracts\WhatsAppProvider;
use App\Enums\NotificationDeliveryStatus;

/**
 * A driver that performs no network call but records every attempt.
 *
 * Bound during automated tests so a suite never depends on a real gateway, a
 * real credential or a real phone number. It reports a genuine "sent" so the
 * delivery bookkeeping is exercised end to end rather than short-circuited, and
 * it can be told to fail once so the failure path is covered too.
 */
class FakeWhatsAppProvider implements WhatsAppProvider
{
    /**
     * @var list<array{to: string, message: string}>
     */
    public static array $sent = [];

    public static bool $configured = true;

    public static ?string $nextFailure = null;

    public static function reset(): void
    {
        static::$sent = [];
        static::$configured = true;
        static::$nextFailure = null;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function isConfigured(): bool
    {
        return static::$configured;
    }

    public function send(string $recipient, string $message): WhatsAppDeliveryResult
    {
        if (! $this->isConfigured()) {
            return WhatsAppDeliveryResult::notConfigured($this->name(), 'Fake provider dinonaktifkan untuk pengujian ini.');
        }

        if (static::$nextFailure !== null) {
            $failure = static::$nextFailure;
            static::$nextFailure = null;

            return new WhatsAppDeliveryResult(NotificationDeliveryStatus::Failed, $this->name(), $failure);
        }

        static::$sent[] = ['to' => $recipient, 'message' => $message];

        return WhatsAppDeliveryResult::sent($this->name(), 'fake-'.count(static::$sent));
    }

    public function unavailableReason(): ?string
    {
        return $this->isConfigured() ? null : 'Fake provider dinonaktifkan untuk pengujian ini.';
    }
}
