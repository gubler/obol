<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\ValueObject\CalendarDate;
use App\ValueObject\Money;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Subscription>
 */
final class SubscriptionFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Subscription::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            // The founder owns factory-built data by default, matching authenticatedClient()'s login
            // so feature tests see their own subscriptions. Isolation tests override with another owner.
            'owner' => UserFactory::founder(),
            'category' => CategoryFactory::new(),
            'color' => self::faker()->randomElement(TileColor::cases()),
            'cost' => new Money(self::faker()->numberBetween(500, 3000), Currency::USD),
            'description' => self::faker()->sentence(),
            // A future calendar date (no time-of-day), so a factory sub defaults to Automated. The random
            // time that once masked the naive/zoned bug is gone: a calendar date has none to begin with.
            'nextRenewal' => CalendarDate::forDatetimeInTimezone(
                \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('now', '+60 days')),
                new \DateTimeZone('UTC'),
            ),
            // Fixed well in the past so nextRenewal is judged future at construction (generation stays
            // Automated); createdAt is stamped to the real now inside the constructor, independent of this.
            'now' => new \DateTimeImmutable('2000-01-01', new \DateTimeZone('UTC')),
            'link' => self::faker()->url(),
            'logo' => '',
            'name' => self::faker()->words(2, true),
            'paymentPeriod' => self::faker()->randomElement(PaymentPeriod::cases()),
            'paymentPeriodCount' => 1,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this;
        // ->afterInstantiate(function(Subscription $subscription): void {})
    }

    public function archived(): self
    {
        return $this->afterInstantiate(function (Subscription $subscription): void {
            $subscription->archive();
        });
    }

    public function withRecentPayment(): self
    {
        return $this->afterInstantiate(function (Subscription $subscription): void {
            PaymentFactory::createOne([
                'subscription' => $subscription,
                'paidDate' => CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('-5 days'), new \DateTimeZone('UTC')),
            ]);
        });
    }

    public function manual(): self
    {
        return $this->afterInstantiate(function (Subscription $subscription): void {
            $subscription->switchToManualPayments();
        });
    }

    public function expensiveSubscription(): self
    {
        return $this->with([
            'cost' => new Money(self::faker()->numberBetween(5000, 15000), Currency::USD),
        ]);
    }
}
