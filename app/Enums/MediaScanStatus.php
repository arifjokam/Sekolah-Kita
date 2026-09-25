<?php

namespace App\Enums;

enum MediaScanStatus: string
{
    case Pending = 'pending';
    case Clean = 'clean';
    case Failed = 'failed';
    case Quarantined = 'quarantined';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pemeriksaan',
            self::Clean => 'Aman',
            self::Failed => 'Gagal diperiksa',
            self::Quarantined => 'Dikarantina',
        };
    }
}
