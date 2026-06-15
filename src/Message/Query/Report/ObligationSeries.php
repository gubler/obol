<?php

// ABOUTME: The obligations-over-time trend: ordered points (oldest first) at one week/month/year granularity.
// ABOUTME: asOf is the rate-read date (today); isApproximate is true when any point needed conversion.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Enum\ObligationTrendPeriod;

final readonly class ObligationSeries
{
    /**
     * @param list<ObligationPoint> $points
     */
    public function __construct(
        public array $points,
        public ObligationTrendPeriod $period,
        public \DateTimeImmutable $asOf,
        public bool $isApproximate,
    ) {
    }
}
