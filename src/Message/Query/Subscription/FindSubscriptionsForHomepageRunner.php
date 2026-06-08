<?php

// ABOUTME: Runner for FindSubscriptionsForHomepageQuery that groups subscriptions by category.
// ABOUTME: Returns a list of CategoryGroup read models, ordered as the repository sorts them.

declare(strict_types=1);

namespace App\Message\Query\Subscription;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Repository\SubscriptionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindSubscriptionsForHomepageQuery::class)]
final readonly class FindSubscriptionsForHomepageRunner
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
    ) {
    }

    /**
     * @return list<CategoryGroup>
     */
    public function __invoke(FindSubscriptionsForHomepageQuery $query): array
    {
        $subscriptions = $this->subscriptionRepository->findForHomepage($query->includeArchived);

        /** @var array<string, array{category: Category, subscriptions: list<Subscription>}> $grouped */
        $grouped = [];
        foreach ($subscriptions as $subscription) {
            $key = $subscription->category->id->toRfc4122();
            $grouped[$key] ??= ['category' => $subscription->category, 'subscriptions' => []];
            $grouped[$key]['subscriptions'][] = $subscription;
        }

        return array_map(
            static fn (array $group): CategoryGroup => new CategoryGroup(
                category: $group['category'],
                subscriptions: $group['subscriptions'],
            ),
            array_values($grouped),
        );
    }
}
