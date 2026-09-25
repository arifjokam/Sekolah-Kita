<?php

namespace App\Enums;

enum MediaProcessingStatus: string
{
    case Quarantined = 'quarantined';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Quarantined => 'Dikarantina',
            self::Processing => 'Diproses',
            self::Ready => 'Siap',
            self::Failed => 'Gagal',
            self::Deleted => 'Dihapus',
        };
    }
}
