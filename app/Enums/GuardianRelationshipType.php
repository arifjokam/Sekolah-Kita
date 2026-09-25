<?php

namespace App\Enums;

enum GuardianRelationshipType: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Grandfather = 'grandfather';
    case Grandmother = 'grandmother';
    case Sibling = 'sibling';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Ayah',
            self::Mother => 'Ibu',
            self::Grandfather => 'Kakek',
            self::Grandmother => 'Nenek',
            self::Sibling => 'Saudara Kandung',
            self::Uncle => 'Paman',
            self::Aunt => 'Bibi',
            self::Other => 'Lainnya',
        };
    }
}
