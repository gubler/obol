<?php

namespace App\Factory;

use App\Entity\Payment;
use App\Enum\PaymentType;
use App\Repository\PaymentRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<Payment>
 */
final class PaymentFactory extends PersistentProxyObjectFactory{
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
        return Payment::class;
    }

        /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'amount' => self::faker()->numberBetween(500, 5000),
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'subscription' => SubscriptionFactory::new(),
            'type' => self::faker()->randomElement(PaymentType::cases()),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(Payment $payment): void {})
        ;
    }

    public function regular(): static
    {
        return $this->with(['type' => PaymentType::Verified]);
    }

    public function generated(): static
    {
        return $this->with(['type' => PaymentType::Generated]);
    }
}
