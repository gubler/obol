<?php

namespace App\Factory;

use App\Entity\SubscriptionEvent;
use App\Enum\SubscriptionEventType;
use App\Repository\SubscriptionEventRepository;
use Doctrine\ORM\EntityRepository;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;
use Zenstruck\Foundry\Persistence\Proxy;
use Zenstruck\Foundry\Persistence\ProxyRepositoryDecorator;

/**
 * @extends PersistentProxyObjectFactory<SubscriptionEvent>
 */
final class SubscriptionEventFactory extends PersistentProxyObjectFactory{
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
        return SubscriptionEvent::class;
    }

        /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array|callable
    {
        return [
            'context' => [],
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'subscription' => SubscriptionFactory::new(),
            'type' => self::faker()->randomElement(SubscriptionEventType::cases()),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            // ->afterInstantiate(function(SubscriptionEvent $subscriptionEvent): void {})
        ;
    }

    public function update(): static
    {
        return $this->with(['type' => SubscriptionEventType::Update]);
    }

    public function costChange(): static
    {
        return $this->with(['type' => SubscriptionEventType::CostChange]);
    }

    public function archive(): static
    {
        return $this->with(['type' => SubscriptionEventType::Archive]);
    }

    public function unarchive(): static
    {
        return $this->with(['type' => SubscriptionEventType::Unarchive]);
    }
}
