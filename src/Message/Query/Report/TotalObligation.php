<?php

// ABOUTME: Read model for the total obligation: converted week/month/year headlines plus a native breakdown.
// ABOUTME: weekly/monthly/yearly are in the display currency; breakdown is the native per-currency monthly split.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\ValueObject\CalendarDate;
use App\ValueObject\Money;

final readonly class TotalObligation
{
    /**
     * @param list<Money> $breakdown native per-currency monthly amounts, key-sorted by currency code
     */
    public function __construct(
        public Money $weekly,
        public Money $monthly,
        public Money $yearly,
        public array $breakdown,
        public CalendarDate $asOf,
        public bool $isApproximate,
    ) {
    }
}
