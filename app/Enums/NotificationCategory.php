<?php

namespace App\Enums;

enum NotificationCategory: string
{
    case Account = 'account';
    case Content = 'content';
    case Contact = 'contact';
    case Admission = 'admission';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Account => 'Akun',
            self::Content => 'Konten',
            self::Contact => 'Kontak',
            self::Admission => 'PPDB',
            self::System => 'Sistem',
        };
    }
}
