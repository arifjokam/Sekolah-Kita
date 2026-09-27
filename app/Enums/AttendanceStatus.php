<?php

namespace App\Enums;

/**
 * A student's attendance for one lesson.
 *
 * PRESENT is the default so an unmarked row is never ambiguous, and the four
 * other states are all explicit absences a school needs to be able to report
 * separately (a medical absence is not the same fact as an unexplained one).
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Excused = 'excused';
    case Sick = 'sick';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::Excused => 'Izin',
            self::Sick => 'Sakit',
            self::Absent => 'Alpa',
        };
    }

    /**
     * Present plus late counts towards attendance: a student who arrived late
     * was in the room.
     */
    public function countsAsAttended(): bool
    {
        return $this === self::Present || $this === self::Late;
    }

    /**
     * A note is mandatory for the states a school has to justify.
     */
    public function requiresNote(): bool
    {
        return $this === self::Sick || $this === self::Excused || $this === self::Absent;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Present => 'bg-emerald-100 text-emerald-800',
            self::Late => 'bg-amber-100 text-amber-800',
            self::Excused => 'bg-blue-100 text-blue-800',
            self::Sick => 'bg-violet-100 text-violet-800',
            self::Absent => 'bg-rose-100 text-rose-800',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
