<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\PaymentSource;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<PaymentSource>
 */
final class PaymentSourceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return PaymentSource::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            // The founder owns factory-built data by default, matching authenticatedClient()'s login so
            // feature tests see their own payment sources. Isolation tests override with another owner.
            'owner' => UserFactory::founder(),
            'name' => self::faker()->words(nb: 2, asText: true),
        ];
    }
}
