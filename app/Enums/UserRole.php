<?php

namespace App\Enums;

enum UserRole: string
{
    case Administrator = 'administrator';
    case Guru = 'guru';
    case Siswa = 'siswa';

    /**
     * A PPDB applicant portal account.
     *
     * A calon is deliberately a fourth role rather than a flag on the siswa role:
     * an applicant has no Student record, no Enrollment and no class, and must
     * never inherit a student-scoped authorization. Keeping the role explicit
     * means the existing role middleware, the policies and the role landing
     * pages all keep working unchanged, and an applicant simply falls outside
     * every guru/administrator/siswa ability.
     */
    case Calon = 'calon';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::Guru => 'Guru',
            self::Siswa => 'Siswa',
            self::Calon => 'Calon Siswa',
        };
    }
}
