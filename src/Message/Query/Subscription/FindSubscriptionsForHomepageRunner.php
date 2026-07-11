<?php

// ABOUTME: Runner for FindSubscriptionsForHomepageQuery that sorts and groups subscriptions for the homepage.
// ABOUTME: Returns a HomepageListing: groups ordered by category name with subscriptions sorted within, plus a flat sorted list.

declare(strict_types=1);

namespace App\Message\Query\Subscription;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\SubscriptionSort;
use App\Message\Currency\CurrencyTotaller;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindSubscriptionsForHomepageQuery::class)]
final readonly class FindSubscriptionsForHomepageRunner
{
    /**
     * Bucket key for subscriptions with no category. The empty string never collides with a
     * category's RFC 4122 id.
     */
    private const string UNCATEGORIZED_KEY = '';

    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private CurrencyTotaller $currencyTotaller,
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(FindSubscriptionsForHomepageQuery $query): HomepageListing
    {
        $subscriptions = $this->subscriptionRepository->findForHomepageForOwner($query->ownerUserId, $query->includeArchived);
        usort($subscriptions, $this->comparator($query->sort));

        $asOf = new \DateTimeImmutable();
        $owner = $this->userRepository->getForId($query->ownerUserId);
        $display = $owner->displayCurrency;
        // The owner's savings-target lead: 0 funds by the due month, 1 a month ahead (see ADR-0009).
        $leadMonths = $owner->savingsDisplay->leadMonths();

        return new HomepageListing(
            groups: $this->group($subscriptions, $display, $asOf, $leadMonths),
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
    private function group(array $subscriptions, Currency $display, \DateTimeImmutable $asOf, int $leadMonths): array
    {
        /** @var array<string, array{category: ?Category, subscriptions: list<Subscription>}> $grouped */
        $grouped = [];
        foreach ($subscriptions as $subscription) {
            // Subscriptions with no category collapse into a single uncategorized bucket.
            $key = $subscription->category?->id->toRfc4122() ?? self::UNCATEGORIZED_KEY;
            $grouped[$key] ??= ['category' => $subscription->category, 'subscriptions' => []];
            $grouped[$key]['subscriptions'][] = $subscription;
        }

        usort(
            $grouped,
            static function (array $a, array $b): int {
                // The uncategorized bucket always sorts after the named categories.
                if (null === $a['category'] || null === $b['category']) {
                    return (null === $a['category'] ? 1 : 0) <=> (null === $b['category'] ? 1 : 0);
                }

                return strcasecmp($a['category']->name, $b['category']->name);
            },
        );

        return array_map(
            fn (array $group): CategoryGroup => new CategoryGroup(
                category: $group['category'],
                subscriptions: $group['subscriptions'],
                monthlyTotal: $this->currencyTotaller->total(
                    array_map(static fn (Subscription $s): Money => $s->monthlyCost(), $group['subscriptions']),
                    $display,
                    $asOf,
                ),
                savingsTotal: $this->currencyTotaller->total(
                    array_map(static fn (Subscription $s): Money => $s->savingsTarget($asOf, $leadMonths), $group['subscriptions']),
                    $display,
                    $asOf,
                ),
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
