<?php

namespace App\Enums;

/**
 * A school week day, stored as ISO-8601 day number (1 = Monday … 7 = Sunday).
 *
 * ISO numbering is used rather than Carbon's 0-based constant so the stored
 * value is stable and readable in the database, and so a "day" never depends on
 * the app's locale.
 */
enum WeekDay: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Senin',
            self::Tuesday => 'Selasa',
            self::Wednesday => 'Rabu',
            self::Thursday => 'Kamis',
            self::Friday => 'Jumat',
            self::Saturday => 'Sabtu',
            self::Sunday => 'Minggu',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Monday => 'Sen',
            self::Tuesday => 'Sel',
            self::Wednesday => 'Rab',
            self::Thursday => 'Kam',
            self::Friday => 'Jum',
            self::Saturday => 'Sab',
            self::Sunday => 'Min',
        };
    }

    public function isWeekend(): bool
    {
        return $this === self::Saturday || $this === self::Sunday;
    }

    public function carbonDayOfWeek(): int
    {
        return $this === self::Sunday ? 0 : $this->value;
    }

    /**
     * @return list<self>
     */
    public static function mondayFirst(): array
    {
        return [
            self::Monday,
            self::Tuesday,
            self::Wednesday,
            self::Thursday,
            self::Friday,
            self::Saturday,
            self::Sunday,
        ];
    }

    /**
     * The days this school teaches on.
     *
     * A six-day school (Mon–Sat) is the default, which is the common case in
     * Indonesia. Sunday only becomes available when the deployment explicitly
     * allows it, so an accidental Sunday slot cannot be created by an
     * administrator who mis-picks a value.
     *
     * @return list<self>
     */
    public static function allowed(): array
    {
        $allowed = array_map(
            static fn (int|string $value): self => self::from((int) $value),
            (array) config('sekolah-kita.schedule.allowed_days', [1, 2, 3, 4, 5, 6]),
        );

        $valid = array_values(array_filter(
            $allowed,
            static fn (self $day): bool => $day !== null,
        ));

        return $valid === [] ? [self::Monday, self::Tuesday, self::Wednesday, self::Thursday, self::Friday] : $valid;
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::mondayFirst() as $day) {
            $options[$day->value] = $day->label();
        }

        return $options;
    }
}
