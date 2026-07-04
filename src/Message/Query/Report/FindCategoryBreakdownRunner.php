<?php

// ABOUTME: Runner for FindCategoryBreakdownQuery: one category's subscriptions as a composition pie.
// ABOUTME: Resolves the category (null when missing), converts each active subscription's monthly share.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Entity\Subscription;
use App\Message\Currency\CurrencyTotaller;
use App\Repository\CategoryRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler(bus: 'query.bus', handles: FindCategoryBreakdownQuery::class)]
final readonly class FindCategoryBreakdownRunner
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private SubscriptionRepository $subscriptionRepository,
        private CurrencyTotaller $currencyTotaller,
        private UserRepository $userRepository,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(FindCategoryBreakdownQuery $query): ?Composition
    {
        // A null category id is the uncategorized drill-down: the subscriptions with no category.
        if (!$query->categoryId instanceof \Symfony\Component\Uid\Ulid) {
            $title = $this->translator->trans('subscription.group.uncategorized');
            $category = null;
        } else {
            $category = $this->categoryRepository->findForOwner($query->categoryId, $query->ownerUserId);
            if (!$category instanceof \App\Entity\Category) {
                return null;
            }

            $title = $category->name;
        }

        $asOf = new \DateTimeImmutable();
        $display = $this->userRepository->getForId($query->ownerUserId)->displayCurrency;
        $subscriptions = $this->subscriptionRepository->findActiveForOwnerByCategory($query->ownerUserId, $category);

        $slices = array_map(
            function (Subscription $subscription) use ($display, $asOf): CompositionSlice {
                $share = $this->currencyTotaller->total([$subscription->monthlyCost()], $display, $asOf);

                return new CompositionSlice(
                    label: $subscription->name,
                    converted: $share->converted,
                    breakdown: $share->breakdown,
                    isApproximate: $share->isApproximate,
                );
            },
            $subscriptions,
        );

        usort(
            $slices,
            static fn (CompositionSlice $a, CompositionSlice $b): int => $b->converted->minorAmount <=> $a->converted->minorAmount,
        );

        $total = $this->currencyTotaller->total(
            array_map(static fn (Subscription $s): Money => $s->monthlyCost(), $subscriptions),
            $display,
            $asOf,
        );

        return new Composition(slices: $slices, total: $total, asOf: $asOf, title: $title);
    }
}
