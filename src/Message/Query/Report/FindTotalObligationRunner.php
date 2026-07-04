<?php

// ABOUTME: Runner for FindTotalObligationQuery: the total obligation over active subscriptions.
// ABOUTME: Totals monthlyCost in the display currency via CurrencyTotaller, then scales week/month/year.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
use App\Message\Currency\CurrencyTotaller;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindTotalObligationQuery::class)]
final readonly class FindTotalObligationRunner
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private CurrencyTotaller $currencyTotaller,
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(FindTotalObligationQuery $query): TotalObligation
    {
        $asOf = new \DateTimeImmutable();
        $display = $this->userRepository->getForId($query->ownerUserId)->displayCurrency;

        $monthlyCosts = array_map(
            static fn (Subscription $subscription): Money => $subscription->monthlyCost(),
            $this->subscriptionRepository->findActiveForOwner($query->ownerUserId),
        );

        $monthly = $this->currencyTotaller->total($monthlyCosts, $display, $asOf);

        return new TotalObligation(
            weekly: $this->scale($monthly->converted, PaymentPeriod::Week->monthsPerPeriod()),
            monthly: $monthly->converted,
            yearly: $this->scale($monthly->converted, PaymentPeriod::Year->monthsPerPeriod()),
            breakdown: $monthly->breakdown,
            asOf: $asOf,
            isApproximate: $monthly->isApproximate,
        );
    }

    private function scale(Money $money, float $factor): Money
    {
        return new Money((int) round($money->minorAmount * $factor), $money->currency);
    }
}
