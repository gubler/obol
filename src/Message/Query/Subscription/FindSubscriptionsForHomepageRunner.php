<?php

// ABOUTME: Runner for FindSubscriptionsForHomepageQuery that sorts and groups subscriptions for the homepage.
// ABOUTME: Returns a HomepageListing: groups ordered by category name with subscriptions sorted within, plus a flat sorted list.

declare(strict_types=1);

namespace App\Message\Query\Subscription;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\SubscriptionSort;
use App\Repository\SubscriptionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindSubscriptionsForHomepageQuery::class)]
final readonly class FindSubscriptionsForHomepageRunner
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
    ) {
    }

    public function __invoke(FindSubscriptionsForHomepageQuery $query): HomepageListing
    {
        $subscriptions = $this->subscriptionRepository->findForHomepage($query->includeArchived);
        usort($subscriptions, $this->comparator($query->sort));

        $asOf = new \DateTimeImmutable();

        return new HomepageListing(
            groups: $this->group($subscriptions, $asOf),
            subscriptions: $subscriptions,
        );
    }

    /**
     * Partition the already-sorted subscriptions into category groups, ordered by category name. Each
     * group keeps the global sort order, so appending in iteration order leaves it sorted within.
     *
     * @param list<Subscription> $subscriptions
     *
     * @return list<CategoryGroup>
     */
    private function group(array $subscriptions, \DateTimeImmutable $asOf): array
    {
        /** @var array<string, array{category: Category, subscriptions: list<Subscription>}> $grouped */
        $grouped = [];
        foreach ($subscriptions as $subscription) {
            $key = $subscription->category->id->toRfc4122();
            $grouped[$key] ??= ['category' => $subscription->category, 'subscriptions' => []];
            $grouped[$key]['subscriptions'][] = $subscription;
        }

        usort(
            $grouped,
            static fn (array $a, array $b): int => strcasecmp($a['category']->name, $b['category']->name),
        );

        return array_map(
            static fn (array $group): CategoryGroup => new CategoryGroup(
                category: $group['category'],
                subscriptions: $group['subscriptions'],
                asOf: $asOf,
            ),
            $grouped,
        );
    }

    /**
     * The comparator for a sort mode. Every mode breaks ties on name so output stays deterministic.
     *
     * @return \Closure(Subscription, Subscription): int
     */
    private function comparator(SubscriptionSort $sort): \Closure
    {
        $byName = static fn (Subscription $a, Subscription $b): int => strcasecmp($a->name, $b->name);

        return match ($sort) {
            SubscriptionSort::Name => $byName,
            SubscriptionSort::Renewal => static fn (Subscription $a, Subscription $b): int => ($a->nextRenewal <=> $b->nextRenewal) ?: $byName($a, $b),
            SubscriptionSort::MonthlyCost => static fn (Subscription $a, Subscription $b): int => ($b->monthlyCost()->minorAmount <=> $a->monthlyCost()->minorAmount) ?: $byName($a, $b),
            SubscriptionSort::Cost => static fn (Subscription $a, Subscription $b): int => ($b->cost->minorAmount <=> $a->cost->minorAmount) ?: $byName($a, $b),
        };
    }
}
