<?php

// ABOUTME: Foundry factory for User accounts (the User constructor creates the primary verified UserEmail).
// ABOUTME: founder() builds the ROLE_ADMIN dogfooding account used as the default login in feature tests.

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
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
     * The founder: the dogfooding admin account (daryl@dev88.co). The default identity for
     * authenticated feature tests; the data-isolation migration seeds the real one.
     */
    public static function founder(): User
    {
        return self::createOne([
            'email' => 'daryl@dev88.co',
            'roles' => ['ROLE_ADMIN'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'roles' => [],
        ];
    }
}
