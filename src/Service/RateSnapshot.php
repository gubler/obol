<?php

// ABOUTME: A parsed set of EUR-pivot rates for one publication date, keyed by ISO currency code.
// ABOUTME: The boundary type returned by an ExchangeRateProvider. Includes EUR pinned to 1.0.

declare(strict_types=1);

namespace App\Service;

use App\ValueObject\CalendarDate;

final readonly class RateSnapshot
{
    /**
     * @param array<string, float> $rates EUR-pivot rates keyed by ISO 4217 code (supported only)
     */
    public function __construct(
        public CalendarDate $date,
        public array $rates,
    ) {
    }
}
