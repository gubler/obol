<?php

// ABOUTME: Query message for finding all payment sources.
// ABOUTME: Dispatched via query.bus and handled by FindAllPaymentSourcesRunner.

declare(strict_types=1);

namespace App\Message\Query\PaymentSource;

use Symfony\Component\Uid\Ulid;

final readonly class FindAllPaymentSourcesQuery
{
    public function __construct(
        public Ulid $ownerUserId,
    ) {
    }
}
