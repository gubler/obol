<?php

// ABOUTME: Handler for ArchiveSubscriptionCommand that archives existing subscription entities.
// ABOUTME: Finds subscription by ID and marks it as archived, flushing changes via Doctrine.

declare(strict_types=1);

namespace App\Message\Command\Subscription;

use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler(bus: 'command.bus', handles: ArchiveSubscriptionCommand::class)]
final readonly class ArchiveSubscriptionHandler
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ArchiveSubscriptionCommand $command): void
    {
        $subscription = $this->subscriptionRepository->find(Ulid::fromString($command->subscriptionId));

        if (null === $subscription) {
            throw new \InvalidArgumentException(\sprintf('Subscription with ID "%s" not found.', $command->subscriptionId));
        }

        $subscription->archive();

        $this->entityManager->flush();
    }
}
