<?php

namespace App\Enums;

/**
 * Delivery state of an out-of-band notification attempt.
 *
 * The point of recording NotConfigured is honesty: when no WhatsApp provider is
 * configured the system must say so rather than reporting a send that never
 * happened.
 */
enum NotificationDeliveryStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case NotConfigured = 'not_configured';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Menunggu pengiriman',
            self::Sent => 'Terkirim',
            self::NotConfigured => 'Belum dikonfigurasi',
            self::Failed => 'Gagal terkirim',
            self::Skipped => 'Dilewati',
        };
    }
}
