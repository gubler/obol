<?php

// ABOUTME: Runner for FindCategoryCompositionQuery: each category's share of the monthly obligation.
// ABOUTME: Groups active subscriptions by category, converts each share via CurrencyTotaller, sorts by size.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\TileColor;
use App\Message\Currency\CurrencyTotaller;
use App\Repository\SubscriptionRepository;
use App\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler(bus: 'query.bus', handles: FindCategoryCompositionQuery::class)]
final readonly class FindCategoryCompositionRunner
{
    /**
     * Bucket key for subscriptions with no category. The empty string never collides with a
     * category's RFC 4122 id.
     */
    private const string UNCATEGORIZED_KEY = '';

    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private CurrencyTotaller $currencyTotaller,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(FindCategoryCompositionQuery $query): Composition
    {
        $asOf = new \DateTimeImmutable();
        $subscriptions = $this->subscriptionRepository->findBy(['archived' => false]);

        /** @var array<string, array{category: ?Category, costs: list<Money>}> $byCategory */
        $byCategory = [];
        foreach ($subscriptions as $subscription) {
            // Subscriptions with no category collapse into a single uncategorized slice.
            $key = $subscription->category?->id->toRfc4122() ?? self::UNCATEGORIZED_KEY;
            $byCategory[$key] ??= ['category' => $subscription->category, 'costs' => []];
            $byCategory[$key]['costs'][] = $subscription->monthlyCost();
        }

        $slices = array_map(
            function (array $group) use ($asOf): CompositionSlice {
                $share = $this->currencyTotaller->total($group['costs'], $asOf);
                $category = $group['category'];

                return new CompositionSlice(
                    label: null !== $category ? $category->name : $this->translator->trans('subscription.group.uncategorized'),
                    converted: $share->converted,
                    breakdown: $share->breakdown,
                    isApproximate: $share->isApproximate,
                    id: $category?->id,
                    uncategorized: null === $category,
                    // Uncategorized takes the reserved neutral Charcoal swatch; its badge icon falls
                    // back to the dashed default (no CategoryIcon).
                    color: null !== $category ? $category->color : TileColor::Charcoal,
                    icon: $category?->icon,
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
