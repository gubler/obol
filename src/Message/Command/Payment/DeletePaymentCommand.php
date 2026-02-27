<?php

// ABOUTME: Command message for deleting a payment.
// ABOUTME: Dispatched via command.bus and handled by DeletePaymentHandler.

declare(strict_types=1);

namespace App\Message\Command\Payment;

final readonly class DeletePaymentCommand
{
    public function __construct(
        public string $paymentId,
    ) {
    }
}
