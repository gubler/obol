<?php

// ABOUTME: Command message for unarchiving a subscription.
// ABOUTME: Dispatched via command.bus and handled by UnarchiveSubscriptionHandler.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use Symfony\Component\Uid\Ulid;

final readonly class UnarchiveSubscriptionCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public Ulid $subscriptionId,
    ) {
    }
}
