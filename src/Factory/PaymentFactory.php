<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Payment;
use App\Enum\Currency;
use App\Enum\PaymentType;
use App\ValueObject\Money;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Payment>
 */
final class PaymentFactory extends PersistentObjectFactory
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
            'amount' => new Money(self::faker()->numberBetween(500, 5000), Currency::USD),
            'paidDate' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
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
