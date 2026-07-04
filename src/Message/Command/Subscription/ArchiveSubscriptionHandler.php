<?php

// ABOUTME: Handler for ArchiveSubscriptionCommand that archives existing subscription entities.
// ABOUTME: Finds subscription by ID and marks it archived; the command bus commits the change.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: ArchiveSubscriptionCommand::class)]
final readonly class ArchiveSubscriptionHandler
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private SubscriptionChangeNotifierInterface $subscriptionChangeNotifier,
    ) {
    }

    public function __invoke(ArchiveSubscriptionCommand $command): void
    {
        $subscription = $this->subscriptionRepository->findForOwner($command->subscriptionId, $command->ownerUserId);

        if (!$subscription instanceof \App\Entity\Subscription) {
            throw new \InvalidArgumentException(\sprintf('Subscription with ID "%s" not found.', $command->subscriptionId));
        }

        $subscription->archive();

        $this->subscriptionChangeNotifier->notifyChanged($command->ownerUserId);
    }
}
