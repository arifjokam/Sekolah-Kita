<?php

namespace App\Contracts;

/**
 * A WhatsApp transport.
 *
 * The interface is intentionally tiny — one destination, one message, one
 * outcome — so a real gateway, a log-only driver and a test double are
 * interchangeable without any of them leaking into the call site.
 *
 * An implementation must never throw for an ordinary transport failure. It
 * reports the outcome through the returned result so a delivery row is always
 * written; only a programming error should propagate.
 */
interface WhatsAppProvider
{
    /**
     * The driver name recorded on the delivery row, e.g. "fonnte" or "null".
     */
    public function name(): string;

    /**
     * Whether this provider can actually send right now.
     *
     * False when the driver is unconfigured, disabled or missing credentials.
     * The application never reports a send it could not perform.
     */
    public function isConfigured(): bool;

    public function send(string $recipient, string $message): WhatsAppDeliveryResult;

    /**
     * A short, non-secret explanation of why the provider is not configured, for
     * an operator-facing status screen.
     */
    public function unavailableReason(): ?string;
}
