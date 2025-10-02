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
        return function () {
            $type = self::faker()->randomElement(SubscriptionEventType::cases());
            $context = match ($type) {
                SubscriptionEventType::Update => ['name' => ['old' => 'Old Name', 'new' => 'New Name']],
                SubscriptionEventType::CostChange => ['cost' => ['old' => 1000, 'new' => 1500]],
                SubscriptionEventType::Archive, SubscriptionEventType::Unarchive => [],
            };

            return [
                'context' => $context,
                'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
                'subscription' => SubscriptionFactory::new(),
                'type' => $type,
            ];
        };
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
        return $this->with([
            'type' => SubscriptionEventType::Update,
            'context' => ['name' => ['old' => 'Old Name', 'new' => 'New Name']],
        ]);
    }

    public function costChange(): static
    {
        return $this->with([
            'type' => SubscriptionEventType::CostChange,
            'context' => ['cost' => ['old' => 1000, 'new' => 1500]],
        ]);
    }

    public function archive(): static
    {
        return $this->with([
            'type' => SubscriptionEventType::Archive,
            'context' => [],
        ]);
    }

    public function unarchive(): static
    {
        return $this->with([
            'type' => SubscriptionEventType::Unarchive,
            'context' => [],
        ]);
    }
}
