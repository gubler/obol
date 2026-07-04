<?php

// ABOUTME: Query message for finding a single payment source by ID.
// ABOUTME: Dispatched via query.bus and handled by FindPaymentSourceRunner.

declare(strict_types=1);

namespace App\Message\Query\PaymentSource;

use Symfony\Component\Uid\Ulid;

final readonly class FindPaymentSourceQuery
{
    public function __construct(
        public Ulid $ownerUserId,
        public Ulid $paymentSourceId,
    ) {
    }
}
