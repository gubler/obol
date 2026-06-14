<?php

// ABOUTME: One point on the obligations-over-time trend: a bucket label and the obligation as of its start.
// ABOUTME: The amount is in the display currency, converted from the snapshot's native map at today's rate.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\ValueObject\Money;

final readonly class ObligationPoint
{
    public function __construct(
        public string $label,
        public Money $amount,
    ) {
    }
}
