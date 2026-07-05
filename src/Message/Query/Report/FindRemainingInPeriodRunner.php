<?php

// ABOUTME: Runner for FindRemainingInPeriodQuery: what is still owed by each calendar period's end.
// ABOUTME: Projects each active sub's nextRenewal forward to the period boundary, then converts the total.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Currency\ConvertedTotal;
use App\Message\Currency\CurrencyTotaller;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\Service\PeriodBoundaries;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindRemainingInPeriodQuery::class)]
final readonly class FindRemainingInPeriodRunner
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private PeriodBoundaries $periodBoundaries,
        private CurrencyTotaller $currencyTotaller,
        private UserRepository $userRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(FindRemainingInPeriodQuery $query): RemainingInPeriod
    {
        // Resolve "now" in the owner's timezone so the calendar-period boundaries land on their local
        // week/month/year, not UTC's (see ADR-0016).
        $owner = $this->userRepository->getForId($query->ownerUserId);
        $asOf = $owner->toLocal($this->clock->now());
        $display = $owner->displayCurrency;
        $subscriptions = $this->subscriptionRepository->findActiveForOwner($query->ownerUserId);

        return new RemainingInPeriod(
            weekly: $this->remaining(PaymentPeriod::Week, $subscriptions, $display, $asOf),
            monthly: $this->remaining(PaymentPeriod::Month, $subscriptions, $display, $asOf),
            yearly: $this->remaining(PaymentPeriod::Year, $subscriptions, $display, $asOf),
            asOf: $asOf,
        );
    }

    /**
     * @param array<Subscription> $subscriptions
     */
    private function remaining(PaymentPeriod $period, array $subscriptions, Currency $display, \DateTimeImmutable $asOf): ConvertedTotal
    {
        $periodEnd = $this->periodBoundaries->endOfPeriod($period, $asOf);

        $amounts = [];
        foreach ($subscriptions as $subscription) {
            $remaining = $subscription->remainingInPeriod($periodEnd);
            if ($remaining->minorAmount > 0) {
                $amounts[] = $remaining;
            }
        }

        return $this->currencyTotaller->total($amounts, $display, $asOf);
    }
}
