<?php

namespace App\Enums;

enum AdmissionDocumentVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Superseded = 'superseded';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu verifikasi',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Ditolak',
            self::Superseded => 'Digantikan',
            self::Expired => 'Kedaluwarsa',
        };
    }
}
