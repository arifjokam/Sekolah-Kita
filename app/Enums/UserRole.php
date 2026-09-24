<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Guru = 'guru';
    case Siswa = 'siswa';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Guru => 'Guru',
            self::Siswa => 'Siswa',
        };
    }
}
