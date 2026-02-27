<?php

// ABOUTME: Command message for archiving a subscription.
// ABOUTME: Dispatched via command.bus and handled by ArchiveSubscriptionHandler.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

final readonly class ArchiveSubscriptionCommand
{
    public function __construct(
        public string $subscriptionId,
    ) {
    }
}
