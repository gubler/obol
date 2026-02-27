<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\SubscriptionEvent;
use App\Enum\SubscriptionEventType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<SubscriptionEvent>
 */
final class SubscriptionEventFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return SubscriptionEvent::class;
    }

    protected function defaults(): callable
    {
        return function () {
            $type = self::faker()->randomElement(SubscriptionEventType::cases());
            if (!$type instanceof SubscriptionEventType) {
                throw new \InvalidArgumentException('Type not an instance of SubscriptionEventType');
            }

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
