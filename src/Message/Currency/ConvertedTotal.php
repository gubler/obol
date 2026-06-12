<?php

// ABOUTME: A total summed across currencies and converted to the display currency, with the native split kept.
// ABOUTME: converted is the display-currency headline; breakdown is the native per-currency decomposition.

declare(strict_types=1);

namespace App\Message\Currency;

use App\ValueObject\Money;

final readonly class ConvertedTotal
{
    /**
     * @param list<Money> $breakdown native per-currency amounts, key-sorted by currency code
     */
    public function __construct(
        public Money $converted,
        public array $breakdown,
        public bool $isApproximate,
    ) {
    }
}
