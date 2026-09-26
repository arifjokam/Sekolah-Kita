<?php

namespace App\Contracts;

/**
 * A notification that can also be delivered over WhatsApp.
 *
 * Implementing this is what makes a notification eligible for the second
 * channel. It returns plain strings so the message can be queued, logged and
 * shown on a status screen without rehydrating the originating model, and so a
 * phone number is never serialised into a queue payload.
 */
interface ContactableNotification
{
    /**
     * @return array{title: string, body: string}
     */
    public function toWhatsApp(): array;
}
