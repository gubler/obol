<?php

// ABOUTME: Runner for FindCategoryCompositionQuery: each category's share of the monthly obligation.
// ABOUTME: Groups active subscriptions by category, converts each share via CurrencyTotaller, sorts by size.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Message\Currency\CurrencyTotaller;
use App\Repository\SubscriptionRepository;
use App\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindCategoryCompositionQuery::class)]
final readonly class FindCategoryCompositionRunner
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private CurrencyTotaller $currencyTotaller,
    ) {
    }

    public function __invoke(FindCategoryCompositionQuery $query): Composition
    {
        $asOf = new \DateTimeImmutable();
        $subscriptions = $this->subscriptionRepository->findBy(['archived' => false]);

        /** @var array<string, array{category: Category, costs: list<Money>}> $byCategory */
        $byCategory = [];
        foreach ($subscriptions as $subscription) {
            $key = $subscription->category->id->toRfc4122();
            $byCategory[$key] ??= ['category' => $subscription->category, 'costs' => []];
            $byCategory[$key]['costs'][] = $subscription->monthlyCost();
        }

        $slices = array_map(
            function (array $group) use ($asOf): CompositionSlice {
                $share = $this->currencyTotaller->total($group['costs'], $asOf);

                return new CompositionSlice(
                    label: $group['category']->name,
                    converted: $share->converted,
                    breakdown: $share->breakdown,
                    isApproximate: $share->isApproximate,
                    id: $group['category']->id,
                );
            },
            array_values($byCategory),
        );

        usort(
            $slices,
            static fn (CompositionSlice $a, CompositionSlice $b): int => $b->converted->minorAmount <=> $a->converted->minorAmount,
        );

        $total = $this->currencyTotaller->total(
            array_map(static fn (Subscription $s): Money => $s->monthlyCost(), $subscriptions),
            $asOf,
        );

        return new Composition(slices: $slices, total: $total, asOf: $asOf);
    }
}
