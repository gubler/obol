<?php

// ABOUTME: Query message for finding a single payment by ID.
// ABOUTME: Dispatched via query.bus and handled by FindPaymentRunner.

declare(strict_types=1);

namespace App\Message\Query\Payment;

use Symfony\Component\Uid\Ulid;

final readonly class FindPaymentQuery
{
    public function __construct(
        public Ulid $ownerUserId,
        public Ulid $paymentId,
    ) {
    }
}
