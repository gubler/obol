<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
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
            'category' => CategoryFactory::new(),
            'color' => self::faker()->randomElement(TileColor::cases()),
            'cost' => self::faker()->numberBetween(500, 3000),
            'description' => self::faker()->sentence(),
            'nextRenewal' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('now', '+60 days')),
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
                'paidDate' => new \DateTimeImmutable('-5 days'),
            ]);
        });
    }

    public function expensiveSubscription(): self
    {
        return $this->with([
            'cost' => self::faker()->numberBetween(5000, 15000),
        ]);
    }
}
