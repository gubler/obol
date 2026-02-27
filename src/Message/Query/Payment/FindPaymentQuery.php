<?php

// ABOUTME: Query message for finding a single payment by ID.
// ABOUTME: Dispatched via query.bus and handled by FindPaymentRunner.

declare(strict_types=1);

namespace App\Message\Query\Payment;

final readonly class FindPaymentQuery
{
    public function __construct(
        public string $paymentId,
    ) {
    }
}
