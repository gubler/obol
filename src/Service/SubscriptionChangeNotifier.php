<?php

// ABOUTME: The single seam for announcing that a user's subscription set changed, on the event bus.
// ABOUTME: Defers the event (DispatchAfterCurrentBusStamp) so subscribers read state after the change commits.

declare(strict_types=1);

namespace App\Service;

use App\Message\Event\SubscriptionsChanged;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Uid\Ulid;

final readonly class SubscriptionChangeNotifier implements SubscriptionChangeNotifierInterface
{
    public function __construct(
        private MessageBusInterface $eventBus,
    ) {
    }

    public function notifyChanged(Ulid $ownerUserId): void
    {
        $this->eventBus->dispatch(new Envelope(new SubscriptionsChanged($ownerUserId), [new DispatchAfterCurrentBusStamp()]));
    }
}
