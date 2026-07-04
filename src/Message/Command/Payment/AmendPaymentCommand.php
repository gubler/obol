<?php

// ABOUTME: Command message for amending (validating/adjusting) a payment.
// ABOUTME: Dispatched via command.bus and handled by AmendPaymentHandler.

declare(strict_types=1);

namespace App\Message\Command\Payment;

use Symfony\Component\Uid\Ulid;

final readonly class AmendPaymentCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public Ulid $paymentId,
        public int $amount,
        public \DateTimeImmutable $paidDate,
    ) {
    }
}
