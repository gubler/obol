<?php

// ABOUTME: Command message for recording a payment on a subscription.
// ABOUTME: Dispatched via command.bus and handled by CreatePaymentHandler.

declare(strict_types=1);

namespace App\Message\Command\Payment;

use Symfony\Component\Uid\Ulid;

final readonly class CreatePaymentCommand
{
    public function __construct(
        public Ulid $subscriptionId,
        public int $amount,
        public \DateTimeImmutable $paidDate,
    ) {
    }
}
