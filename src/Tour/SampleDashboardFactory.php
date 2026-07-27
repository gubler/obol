<?php

// ABOUTME: Builds the product tour's staged dashboard from a single in-memory, never-persisted subscription.
// ABOUTME: Mirrors the homepage read models (listing + totals) so the tour reuses the real templates verbatim.

declare(strict_types=1);

namespace App\Tour;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\CategoryIcon;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\Message\Currency\ConvertedTotal;
use App\Message\Query\Report\TotalObligation;
use App\Message\Query\Subscription\CategoryGroup;
use App\Message\Query\Subscription\HomepageListing;
use App\ValueObject\Money;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The sample is presentation scaffolding, not domain data: it is constructed in memory and rendered,
 * but never handed to the EntityManager, so it cannot be persisted and cannot pollute real totals or
 * reports. Because the sample uses the owner's own display currency, no conversion is needed - the
 * totals are exact, and the currency Converter (and its rate data) is never touched.
 */
final readonly class SampleDashboardFactory
{
    public function __construct(
        private ClockInterface $clock,
        private TranslatorInterface $translator,
    ) {
    }

    public function forOwner(User $owner): SampleDashboard
    {
        $now = $this->clock->now();
        $asOf = $owner->localDateFor($now);

        $category = new Category(
            owner: $owner,
            name: $this->translator->trans('tour.sample.category'),
            color: TileColor::Emerald,
            icon: CategoryIcon::CreditCard,
        );

        $subscription = new Subscription(
            owner: $owner,
            category: $category,
            name: $this->translator->trans('tour.sample.name'),
            // A comfortably future renewal keeps the sample on automated generation (no "paused" badge)
            // and reads as a friendly "in a couple of weeks" on the tile.
            nextRenewal: $asOf->plusDays(12),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(999, $owner->displayCurrency),
            now: $now,
        );

        $monthly = $subscription->monthlyCost();

        $savings = $owner->savingsDisplay->showsSavings()
            ? $this->exactTotal($subscription->savingsTarget($asOf, $owner->savingsDisplay->leadMonths()))
            : null;

        $group = new CategoryGroup(
            category: $category,
            subscriptions: [$subscription],
            monthlyTotal: $this->exactTotal($monthly),
            savingsTotal: $savings,
            asOf: $asOf,
        );

        $capstone = new TotalObligation(
            weekly: $this->scale($monthly, PaymentPeriod::Week->monthsPerPeriod()),
            monthly: $monthly,
            yearly: $this->scale($monthly, PaymentPeriod::Year->monthsPerPeriod()),
            breakdown: [$monthly],
            asOf: $asOf,
            isApproximate: false,
        );

        return new SampleDashboard(
            listing: new HomepageListing(groups: [$group], subscriptions: [$subscription]),
            capstone: $capstone,
        );
    }

    /**
     * A single-currency total needs no conversion, so it is exact: the display figure is the amount
     * itself and the native breakdown is just that one amount.
     */
    private function exactTotal(Money $amount): ConvertedTotal
    {
        return new ConvertedTotal(converted: $amount, breakdown: [$amount], isApproximate: false);
    }

    private function scale(Money $money, float $factor): Money
    {
        return new Money((int) round($money->minorAmount * $factor), $money->currency);
    }
}
