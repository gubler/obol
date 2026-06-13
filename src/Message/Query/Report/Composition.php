<?php

// ABOUTME: A composition pie's data: its slices, the overall converted total, and the rate-read date.
// ABOUTME: title names the subject when it comes from data (a category, on drill-down); null for the by-category view.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Message\Currency\ConvertedTotal;

final readonly class Composition
{
    /**
     * @param list<CompositionSlice> $slices
     */
    public function __construct(
        public array $slices,
        public ConvertedTotal $total,
        public \DateTimeImmutable $asOf,
        public ?string $title = null,
    ) {
    }
}
