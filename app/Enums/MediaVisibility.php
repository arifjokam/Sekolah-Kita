<?php

namespace App\Enums;

enum MediaVisibility: string
{
    case Private = 'private';
    case Public = 'public';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Privat',
            self::Public => 'Publik',
        };
    }
}
