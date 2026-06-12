<?php

// ABOUTME: Runner for FindTotalObligationQuery: the total obligation over active subscriptions.
// ABOUTME: Sums monthlyCost by native currency, converts to the display currency, and scales week/month/year.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Enum\PaymentPeriod;
use App\Message\Currency\Converter;
use App\Repository\SubscriptionRepository;
use App\Service\DisplayCurrencyProvider;
use App\ValueObject\Money;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindTotalObligationQuery::class)]
final readonly class FindTotalObligationRunner
{
    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
        private Converter $converter,
        private DisplayCurrencyProvider $displayCurrencyProvider,
    ) {
    }

    public function __invoke(FindTotalObligationQuery $query): TotalObligation
    {
        $display = $this->displayCurrencyProvider->get();
        $asOf = new \DateTimeImmutable();

        $native = $this->nativeMonthlyByCurrency();

        $monthly = new Money(0, $display);
        $approximate = false;
        foreach ($native as $amount) {
            if ($amount->currency !== $display) {
                $approximate = true;
            }
            $monthly = $monthly->add($this->converter->convert($amount, $display, $asOf));
        }

        return new TotalObligation(
            weekly: $this->scale($monthly, PaymentPeriod::Week->monthsPerPeriod()),
            monthly: $monthly,
            yearly: $this->scale($monthly, PaymentPeriod::Year->monthsPerPeriod()),
            breakdown: array_values($native),
            asOf: $asOf,
            isApproximate: $approximate,
        );
    }

    /**
     * The monthly obligation per native currency over active subscriptions, key-sorted by currency
     * code so the breakdown and any change comparison have a deterministic order.
     *
     * @return array<string, Money>
     */
    private function nativeMonthlyByCurrency(): array
    {
        $native = [];
        foreach ($this->subscriptionRepository->findBy(['archived' => false]) as $subscription) {
            $monthly = $subscription->monthlyCost();
            $code = $monthly->currency->value;
            $native[$code] = isset($native[$code]) ? $native[$code]->add($monthly) : $monthly;
        }

        ksort($native);

        return $native;
    }

    private function scale(Money $money, float $factor): Money
    {
        return new Money((int) round($money->minorAmount * $factor), $money->currency);
    }
}
