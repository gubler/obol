<?php

// ABOUTME: Handler for UnarchiveSubscriptionCommand that unarchives subscription entities.
// ABOUTME: Finds subscription by ID and marks it active; the command bus commits the change.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: UnarchiveSubscriptionCommand::class)]
final readonly class UnarchiveSubscriptionHandler
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private SubscriptionChangeNotifierInterface $subscriptionChangeNotifier,
    ) {
    }

    public function __invoke(UnarchiveSubscriptionCommand $command): void
    {
        $subscription = $this->subscriptionRepository->find($command->subscriptionId);

        if (null === $subscription) {
            throw new \InvalidArgumentException(\sprintf('Subscription with ID "%s" not found.', $command->subscriptionId));
        }

        $subscription->unarchive();

        $this->subscriptionChangeNotifier->notifyChanged();
    }
}
