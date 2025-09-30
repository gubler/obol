<?php

namespace App\Factory;

use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Subscription>
 */
final class SubscriptionFactory extends PersistentProxyObjectFactory{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct()
    {
    }

    public static function class(): string
    {
        return Subscription::class;
    }

        /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    protected function defaults(): array|callable    {
        return [
            'category' => CategoryFactory::new(),
            'cost' => self::faker()->randomNumber(),
            'description' => self::faker()->text(),
            'lastPaidDate' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'link' => self::faker()->text(),
            'logo' => self::faker()->text(255),
            'name' => self::faker()->text(255),
            'paymentPeriod' => self::faker()->randomElement(PaymentPeriod::cases()),
            'paymentPeriodCount' => self::faker()->randomNumber(),
        ];
    }

        /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Subscription $subscription): void {})
        ;
    }
}
