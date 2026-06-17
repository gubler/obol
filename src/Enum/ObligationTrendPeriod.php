<?php

// ABOUTME: The week/month/year granularity of the obligations-over-time trend, backed by its URL query value.
// ABOUTME: Carries the lookback length, the step unit for walking back, and the x-axis label format.

declare(strict_types=1);

namespace App\Enum;

enum ObligationTrendPeriod: string
{
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /**
     * Resolve a `trend` query value to a case, falling back to the default for unknown or missing input.
     */
    public static function fromQuery(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Month;
    }

    public function label(): string
    {
        // Translation keys resolved in the `messages` catalog; see ADR-0012.
        return match ($this) {
            self::Week => 'enum.obligation_trend_period.week',
            self::Month => 'enum.obligation_trend_period.month',
            self::Year => 'enum.obligation_trend_period.year',
        };
    }

    /**
     * How many buckets (periods) the trend looks back over, ending with the current in-progress one.
     */
    public function lookback(): int
    {
        return match ($this) {
            self::Week => 52,
            self::Month => 24,
            self::Year => 10,
        };
    }

    /**
     * The relative-time unit for stepping back one bucket (used as "-N <unit>" with \DateTimeImmutable::modify).
     */
    public function stepUnit(): string
    {
        return match ($this) {
            self::Week => 'weeks',
            self::Month => 'months',
            self::Year => 'years',
        };
    }

    /**
     * The date() format for a bucket's x-axis label.
     */
    public function labelFormat(): string
    {
        return match ($this) {
            self::Week => 'M j',
            self::Month => 'M Y',
            self::Year => 'Y',
        };
    }
}
