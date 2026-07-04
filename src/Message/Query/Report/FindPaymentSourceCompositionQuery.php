<?php

// ABOUTME: Query for the payment-source-composition pie: each source's share of the monthly obligation.
// ABOUTME: Dispatched via query.bus and handled by FindPaymentSourceCompositionRunner.

declare(strict_types=1);

namespace App\Message\Query\Report;

use Symfony\Component\Uid\Ulid;

final readonly class FindPaymentSourceCompositionQuery
{
    public function __construct(
        public Ulid $ownerUserId,
    ) {
    }
}
