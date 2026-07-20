<?php

// ABOUTME: Immutable timezone-less calendar date (year/month/day) - a day on the wall, not an instant.
// ABOUTME: Crossing to/from a \DateTimeImmutable requires naming a zone, so naive/zoned confusion can't happen by accident. See ADR-0021.

declare(strict_types=1);

namespace App\ValueObject;

use Assert\Assertion;

final readonly class CalendarDate implements \Stringable
{
    private function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {
        // checkdate() validates month (1-12), day (1-days-in-month), year (1-32767), and leap rules in one
        // call, so there is nothing else to assert. "No time, no offset" is structural: there is no field
        // that could hold one.
        Assertion::true(
            checkdate($month, $day, $year),
            \sprintf('Not a valid calendar date: %d-%d-%d', $year, $month, $day),
        );
    }

    public static function for(int $year, int $month, int $day): self
    {
        return new self($year, $month, $day);
    }

    /**
     * A strict `Y-m-d` string and nothing else. The round-trip identity check is what rejects a rolled date
     * (`2026-02-30`), a non-padded value (`2026-8-1`), a relative expression (`today`), a time component,
     * an offset, and trailing junk - PHP's parser is otherwise lenient about all of them.
     */
    public static function fromString(string $date): self
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));

        Assertion::isInstanceOf($parsed, \DateTimeImmutable::class, \sprintf('Not a Y-m-d date: "%s"', $date));
        Assertion::same($parsed->format('Y-m-d'), $date, \sprintf('Not a strict Y-m-d date: "%s"', $date));

        return new self((int) $parsed->format('Y'), (int) $parsed->format('n'), (int) $parsed->format('j'));
    }

    /**
     * The calendar date `$datetime` falls on when read in `$tz` - the one place an instant becomes a date.
     * The zone is required on purpose: this is the naive/zoned boundary, and naming the zone at the call
     * site is what stops the confusion this whole type exists to prevent.
     */
    public static function forDatetimeInTimezone(\DateTimeImmutable $datetime, \DateTimeZone $tz): self
    {
        $local = $datetime->setTimezone($tz);

        return new self((int) $local->format('Y'), (int) $local->format('n'), (int) $local->format('j'));
    }

    public function compareTo(self $other): int
    {
        return [$this->year, $this->month, $this->day] <=> [$other->year, $other->month, $other->day];
    }

    public function equals(self $other): bool
    {
        return 0 === $this->compareTo($other);
    }

    public function isBefore(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isAfter(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    public function isOnOrBefore(self $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    public function isOnOrAfter(self $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    public function plusDays(int $days): self
    {
        // Computed in UTC so a DST-shortened or -lengthened local day cannot truncate or round the count:
        // a calendar day is a calendar day regardless of the ambient zone.
        $shifted = $this->toDateTimeImmutable(new \DateTimeZone('UTC'))->modify(\sprintf('%+d days', $days));

        return new self((int) $shifted->format('Y'), (int) $shifted->format('n'), (int) $shifted->format('j'));
    }

    public function plusWeeks(int $weeks): self
    {
        return $this->plusDays($weeks * 7);
    }

    public function daysUntil(self $other): int
    {
        $from = $this->toDateTimeImmutable(new \DateTimeZone('UTC'));
        $to = $other->toDateTimeImmutable(new \DateTimeZone('UTC'));

        return (int) $from->diff($to)->format('%r%a');
    }

    public function daysInMonth(): int
    {
        return (int) $this->toDateTimeImmutable(new \DateTimeZone('UTC'))->format('t');
    }

    public function lastDayOfMonth(): self
    {
        return new self($this->year, $this->month, $this->daysInMonth());
    }

    /**
     * Midnight on this date in `$tz`. Where local midnight does not exist (a spring-forward transition at
     * 00:00) PHP resolves the construction to the first instant that does - 01:00 - which is the correct
     * start of that local day. The zone is required: there is no ambient default to fall back to.
     */
    public function toDateTimeImmutable(\DateTimeZone $tz): \DateTimeImmutable
    {
        return new \DateTimeImmutable(
            \sprintf('%04d-%02d-%02d 00:00:00', $this->year, $this->month, $this->day),
            $tz,
        );
    }

    public function monthOrdinal(): int
    {
        return $this->year * 12 + $this->month - 1;
    }

    /**
     * Day of the week as 0 (Sunday) through 6 (Saturday), matching PHP's `w` and the app.week_start_day
     * convention.
     */
    public function dayOfWeek(): int
    {
        return (int) $this->toDateTimeImmutable(new \DateTimeZone('UTC'))->format('w');
    }

    public function __toString(): string
    {
        return \sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }
}
