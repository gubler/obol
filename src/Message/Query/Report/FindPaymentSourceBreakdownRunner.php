<?php

// ABOUTME: Runner for FindPaymentSourceBreakdownQuery: one source's subscriptions as a composition pie.
// ABOUTME: Resolves the source (null when missing), converts each active subscription's monthly share.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Entity\Subscription;
use App\Message\Currency\CurrencyTotaller;
use App\Repository\PaymentSourceRepository;
use App\Repository\SubscriptionRepository;
use App\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler(bus: 'query.bus', handles: FindPaymentSourceBreakdownQuery::class)]
final readonly class FindPaymentSourceBreakdownRunner
{
    public function __construct(
        private PaymentSourceRepository $paymentSourceRepository,
        private SubscriptionRepository $subscriptionRepository,
        private CurrencyTotaller $currencyTotaller,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(FindPaymentSourceBreakdownQuery $query): ?Composition
    {
        // A null source id is the unassigned drill-down: the subscriptions with no payment source.
        if (null === $query->paymentSourceId) {
            $title = $this->translator->trans('subscription.group.unassigned');
            $filter = ['archived' => false, 'paymentSource' => null];
        } else {
            $source = $this->paymentSourceRepository->find($query->paymentSourceId);
            if (null === $source) {
                return null;
            }

            $title = $source->name;
            $filter = ['archived' => false, 'paymentSource' => $source];
        }

        $asOf = new \DateTimeImmutable();
        $subscriptions = $this->subscriptionRepository->findBy($filter);

        $slices = array_map(
            function (Subscription $subscription) use ($asOf): CompositionSlice {
                $share = $this->currencyTotaller->total([$subscription->monthlyCost()], $asOf);

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
            $asOf,
        );

        return new Composition(slices: $slices, total: $total, asOf: $asOf, title: $title);
    }
}
