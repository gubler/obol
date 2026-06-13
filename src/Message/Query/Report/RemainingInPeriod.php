<?php

// ABOUTME: Read model for remaining-in-period: what is still owed by the end of week / month / year.
// ABOUTME: Each period is its own ConvertedTotal (a distinct set of renewals), in the display currency.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Message\Currency\ConvertedTotal;

final readonly class RemainingInPeriod
{
    public function __construct(
        public ConvertedTotal $weekly,
        public ConvertedTotal $monthly,
        public ConvertedTotal $yearly,
        public \DateTimeImmutable $asOf,
    ) {
    }
}
