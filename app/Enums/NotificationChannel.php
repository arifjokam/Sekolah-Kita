<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Email = 'email';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::InApp => 'Dalam aplikasi',
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
        };
    }
}
