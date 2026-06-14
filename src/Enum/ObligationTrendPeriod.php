<?php

// ABOUTME: The day/week/month granularity of the obligations-over-time trend, backed by its URL query value.
// ABOUTME: Carries the lookback length, the step unit for walking back, and the x-axis label format.

declare(strict_types=1);

namespace App\Enum;

enum ObligationTrendPeriod: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

    /**
     * Resolve a `trend` query value to a case, falling back to the default for unknown or missing input.
     */
    public static function fromQuery(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Month;
    }

    public function label(): string
    {
        return match ($this) {
            self::Day => 'Daily',
            self::Week => 'Weekly',
            self::Month => 'Monthly',
        };
    }

    /**
     * How many buckets (periods) the trend looks back over, ending with the current in-progress one.
     */
    public function lookback(): int
    {
        return match ($this) {
            self::Day => 14,
            self::Week => 8,
            self::Month => 6,
        };
    }

    /**
     * The relative-time unit for stepping back one bucket (used as "-N <unit>" with \DateTimeImmutable::modify).
     */
    public function stepUnit(): string
    {
        return match ($this) {
            self::Day => 'days',
            self::Week => 'weeks',
            self::Month => 'months',
        };
    }

    /**
     * The date() format for a bucket's x-axis label.
     */
    public function labelFormat(): string
    {
        return match ($this) {
            self::Day, self::Week => 'M j',
            self::Month => 'M Y',
        };
    }
}
