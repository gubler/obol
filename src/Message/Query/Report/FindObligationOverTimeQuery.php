<?php

// ABOUTME: Query for the obligations-over-time trend at a chosen week/month/year granularity.
// ABOUTME: Dispatched via query.bus and handled by FindObligationOverTimeRunner.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Enum\ObligationTrendPeriod;

final readonly class FindObligationOverTimeQuery
{
    public function __construct(
        public ObligationTrendPeriod $period,
    ) {
    }
}
