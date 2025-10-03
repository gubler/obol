<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Subscription>
 */
final class SubscriptionFactory extends PersistentProxyObjectFactory
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
            'cost' => self::faker()->numberBetween(500, 3000),
            'description' => self::faker()->sentence(),
            'lastPaidDate' => \DateTimeImmutable::createFromMutable(self::faker()->dateTimeBetween('-60 days', 'now')),
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
                'createdAt' => new \DateTimeImmutable('-5 days'),
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
