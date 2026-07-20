<?php

// ABOUTME: Runner for FindPaymentSourceBreakdownQuery: one source's subscriptions as a composition pie.
// ABOUTME: Resolves the source (null when missing), converts each active subscription's monthly share.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Entity\Subscription;
use App\Message\Currency\CurrencyTotaller;
use App\Repository\PaymentSourceRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\ValueObject\Money;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler(bus: 'query.bus', handles: FindPaymentSourceBreakdownQuery::class)]
final readonly class FindPaymentSourceBreakdownRunner
{
    public function __construct(
        private PaymentSourceRepository $paymentSourceRepository,
        private SubscriptionRepository $subscriptionRepository,
        private CurrencyTotaller $currencyTotaller,
        private UserRepository $userRepository,
        private ClockInterface $clock,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(FindPaymentSourceBreakdownQuery $query): ?Composition
    {
        // A null source id is the unassigned drill-down: the subscriptions with no payment source.
        if (!$query->paymentSourceId instanceof \Symfony\Component\Uid\Ulid) {
            $title = $this->translator->trans('subscription.group.unassigned');
            $source = null;
        } else {
            $source = $this->paymentSourceRepository->findForOwner($query->paymentSourceId, $query->ownerUserId);
            if (!$source instanceof \App\Entity\PaymentSource) {
                return null;
            }

            $title = $source->name;
        }

        $owner = $this->userRepository->getForId($query->ownerUserId);
        $asOf = $owner->localDateFor($this->clock->now());
        $display = $owner->displayCurrency;
        $subscriptions = $this->subscriptionRepository->findActiveForOwnerByPaymentSource($query->ownerUserId, $source);

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
