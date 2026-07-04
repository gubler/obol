<?php

// ABOUTME: Foundry factory for User accounts (the User constructor creates the primary verified UserEmail).
// ABOUTME: founder() builds the ROLE_ADMIN dogfooding account used as the default login in feature tests.

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use App\Enum\Currency;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    /**
     * The default admin identity for authenticated feature tests and the default owner of
     * factory-built data. Idempotent, so it is safe to call from both authenticatedClient() and a
     * factory's owner default within one test. This is a non-personal stand-in; the data-isolation
     * migration seeds the real founder account.
     */
    public const string FOUNDER_EMAIL = 'founder@example.com';

    public static function founder(): User
    {
        return self::repository()->findOneBy(['email' => self::FOUNDER_EMAIL])
            ?? self::createOne(['email' => self::FOUNDER_EMAIL, 'roles' => ['ROLE_ADMIN']]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'roles' => [],
            'displayCurrency' => Currency::USD,
            'locale' => 'en-US',
            'timezone' => 'America/New_York',
        ];
    }
}
