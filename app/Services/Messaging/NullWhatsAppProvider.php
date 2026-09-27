<?php

namespace App\Services\Messaging;

use App\Contracts\WhatsAppDeliveryResult;
use App\Contracts\WhatsAppProvider;

/**
 * The default driver: no WhatsApp gateway is wired up.
 *
 * This is the honest default. With WHATSAPP_DRIVER unset the application still
 * delivers every in-app notification, and each WhatsApp attempt is recorded as
 * NotConfigured with a reason, so nothing anywhere claims a message was sent that
 * never left the server.
 */
class NullWhatsAppProvider implements WhatsAppProvider
{
    public function name(): string
    {
        return 'null';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function send(string $recipient, string $message): WhatsAppDeliveryResult
    {
        return WhatsAppDeliveryResult::notConfigured(
            $this->name(),
            $this->unavailableReason() ?? 'WhatsApp provider is not configured.',
        );
    }

    public function unavailableReason(): ?string
    {
        return 'WHATSAPP_DRIVER belum diarahkan ke gateway WhatsApp. Notifikasi dalam aplikasi tetap berfungsi.';
    }
}
