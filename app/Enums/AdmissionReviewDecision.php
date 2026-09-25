<?php

namespace App\Enums;

enum AdmissionReviewDecision: string
{
    case Accept = 'accept';
    case Reject = 'reject';
    case Waitlist = 'waitlist';

    public function label(): string
    {
        return match ($this) {
            self::Accept => 'Terima',
            self::Reject => 'Tolak',
            self::Waitlist => 'Daftar tunggu',
        };
    }
}
