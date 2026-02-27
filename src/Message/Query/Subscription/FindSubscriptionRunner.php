<?php

// ABOUTME: Runner for FindSubscriptionQuery that retrieves a single subscription by ID.
// ABOUTME: Returns Subscription entity or null if not found or ID is invalid.

declare(strict_types=1);

namespace App\Message\Query\Subscription;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Ulid;

#[AsMessageHandler(bus: 'query.bus', handles: FindSubscriptionQuery::class)]
final readonly class FindSubscriptionRunner
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function __invoke(FindSubscriptionQuery $query): ?Subscription
    {
        if (!Ulid::isValid($query->subscriptionId)) {
            return null;
        }

        return $this->subscriptionRepository->find(Ulid::fromString($query->subscriptionId));
    }
}
