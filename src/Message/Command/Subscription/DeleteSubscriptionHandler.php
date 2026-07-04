<?php

// ABOUTME: Handler for DeleteSubscriptionCommand that removes subscription entities.
// ABOUTME: Validates subscription has no subscriptions before deletion, throws exception if it does.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: DeleteSubscriptionCommand::class)]
final readonly class DeleteSubscriptionHandler
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private EntityManagerInterface $entityManager,
        private SubscriptionChangeNotifierInterface $subscriptionChangeNotifier,
    ) {
    }

    public function __invoke(DeleteSubscriptionCommand $command): void
    {
        $subscription = $this->subscriptionRepository->findForOwner($command->subscriptionId, $command->ownerUserId);

        if (!$subscription instanceof \App\Entity\Subscription) {
            throw new \InvalidArgumentException(\sprintf('Subscription with ID "%s" not found.', $command->subscriptionId));
        }

        $this->entityManager->remove($subscription);

        $this->subscriptionChangeNotifier->notifyChanged();
    }
}
