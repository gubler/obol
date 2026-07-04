<?php

// ABOUTME: Command message for deleting a payment source.
// ABOUTME: Dispatched via command.bus and handled by DeletePaymentSourceHandler.

declare(strict_types=1);

namespace App\Message\Command\PaymentSource;

use Symfony\Component\Uid\Ulid;

final readonly class DeletePaymentSourceCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public Ulid $paymentSourceId,
    ) {
    }
}
