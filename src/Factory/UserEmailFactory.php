<?php

// ABOUTME: Foundry factory for UserEmail rows - secondary addresses on an existing User.
// ABOUTME: Defaults to a verified, non-primary address; unverified() drops the verification.

declare(strict_types=1);

namespace App\Factory;

use App\Entity\UserEmail;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<UserEmail>
 */
final class UserEmailFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return UserEmail::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'email' => self::faker()->unique()->safeEmail(),
            'isPrimary' => false,
            'verifiedAt' => new \DateTimeImmutable(),
        ];
    }

    /**
     * A secondary address that has not completed verification (cannot be used to log in).
     */
    public function unverified(): self
    {
        return $this->with(['isPrimary' => false, 'verifiedAt' => null]);
    }
}
