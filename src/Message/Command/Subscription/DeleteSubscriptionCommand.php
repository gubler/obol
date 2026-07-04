<?php

// ABOUTME: Command message for deleting a subscription.
// ABOUTME: Dispatched via command.bus and handled by DeleteSubscriptionHandler.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use Symfony\Component\Uid\Ulid;

final readonly class DeleteSubscriptionCommand
{
    public function __construct(
        public Ulid $ownerUserId,
        public Ulid $subscriptionId,
    ) {
    }
}
