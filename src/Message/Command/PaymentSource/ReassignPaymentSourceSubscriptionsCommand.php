<?php

// ABOUTME: Command message for moving every subscription from one payment source to another.
// ABOUTME: Dispatched via command.bus and handled by ReassignPaymentSourceSubscriptionsHandler.

declare(strict_types=1);

namespace App\Message\Command\PaymentSource;

use Symfony\Component\Uid\Ulid;

final readonly class ReassignPaymentSourceSubscriptionsCommand
{
    public function __construct(
        public Ulid $fromPaymentSourceId,
        public Ulid $toPaymentSourceId,
    ) {
    }
}
