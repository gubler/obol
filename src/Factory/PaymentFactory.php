<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Payment;
use App\Enum\PaymentType;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Payment>
 */
final class PaymentFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Payment::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'amount' => self::faker()->numberBetween(500, 5000),
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'subscription' => SubscriptionFactory::new(),
            'type' => self::faker()->randomElement(PaymentType::cases()),
        ];
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
