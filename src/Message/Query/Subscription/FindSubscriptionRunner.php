<?php

// ABOUTME: Runner for FindSubscriptionQuery that retrieves a single subscription by ID.
// ABOUTME: Returns the Subscription entity, or null when not found.

declare(strict_types=1);

namespace App\Message\Query\Subscription;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindSubscriptionQuery::class)]
final readonly class FindSubscriptionRunner
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function __invoke(FindSubscriptionQuery $query): ?Subscription
    {
        return $this->subscriptionRepository->find($query->subscriptionId);
    }
}
