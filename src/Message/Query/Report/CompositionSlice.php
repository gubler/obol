<?php

// ABOUTME: One wedge of a composition pie: a label, its converted share, and the native per-currency split.
// ABOUTME: id is the entity the slice stands for (a category, for linking to its drill-down); null when there is none.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\ValueObject\Money;
use Symfony\Component\Uid\Ulid;

final readonly class CompositionSlice
{
    /**
     * @param list<Money> $breakdown native per-currency amounts, key-sorted by currency code
     */
    public function __construct(
        public string $label,
        public Money $converted,
        public array $breakdown,
        public bool $isApproximate,
        public ?Ulid $id = null,
    ) {
    }
}
