<?php

// ABOUTME: Command message for unarchiving a subscription.
// ABOUTME: Dispatched via command.bus and handled by UnarchiveSubscriptionHandler.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

final readonly class UnarchiveSubscriptionCommand
{
    public function __construct(
        public string $subscriptionId,
    ) {
    }
}
