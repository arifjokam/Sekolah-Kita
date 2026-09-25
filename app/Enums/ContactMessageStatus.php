<?php

namespace App\Enums;

enum ContactMessageStatus: string
{
    case New = 'new';
    case InReview = 'in_review';
    case Responded = 'responded';
    case Spam = 'spam';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::InReview => 'Ditinjau',
            self::Responded => 'Direspons',
            self::Spam => 'Spam',
            self::Archived => 'Diarsipkan',
        };
    }
}
