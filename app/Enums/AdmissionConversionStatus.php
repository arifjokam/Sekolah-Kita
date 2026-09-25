<?php

namespace App\Enums;

enum AdmissionConversionStatus: string
{
    case NotStarted = 'not_started';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Belum dimulai',
            self::Pending => 'Diproses',
            self::Completed => 'Selesai',
            self::Failed => 'Gagal',
        };
    }
}
