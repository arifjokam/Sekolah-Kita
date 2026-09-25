<?php

namespace App\Enums;

enum MediaAssetKind: string
{
    case Image = 'image';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Image => 'Gambar',
            self::Document => 'Dokumen',
        };
    }
}
