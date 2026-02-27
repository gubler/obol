<?php

// ABOUTME: Runner for FindAllSubscriptionsQuery that retrieves all subscriptions.
// ABOUTME: Returns array of Subscription entities ordered by name.

declare(strict_types=1);

namespace App\Message\Query\Subscription;

use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindAllSubscriptionsQuery::class)]
final readonly class FindAllSubscriptionsRunner
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
    ) {
    }

    /**
     * @return array<Subscription>
     */
    public function __invoke(FindAllSubscriptionsQuery $query): array
    {
        return $this->subscriptionRepository->findBy([], ['name' => 'ASC']);
    }
}
