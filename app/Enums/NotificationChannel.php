<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::InApp => 'Dalam aplikasi',
            self::Email => 'Email',
        };
    }
}
