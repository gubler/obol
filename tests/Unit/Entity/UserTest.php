<?php

// ABOUTME: Unit tests for the User entity - role handling, identity, equality, and primary-email cache sync.
// ABOUTME: User is the passwordless account; UserEmail rows carry the addresses (see UserEmailTest).

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Entity\UserEmail;
use App\Enum\Currency;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testDefaultsToUsdDisplayCurrencyAndAppDefaultLocaleAndTimezone(): void
    {
        $user = new User(email: 'magos@dev88.test');

        self::assertSame(Currency::USD, $user->displayCurrency);
        self::assertSame('en-US', $user->locale);
        self::assertSame('America/New_York', $user->timezone);
    }

    public function testCarriesExplicitDisplayCurrencyLocaleAndTimezone(): void
    {
        $user = new User(
            email: 'magos@dev88.test',
            displayCurrency: Currency::EUR,
            locale: 'de',
            timezone: 'Europe/Berlin',
        );

        self::assertSame(Currency::EUR, $user->displayCurrency);
        self::assertSame('de', $user->locale);
        self::assertSame('Europe/Berlin', $user->timezone);
    }

    public function testAlwaysCarriesRoleUserAndDedupesExplicitRoles(): void
    {
        $user = new User(email: 'magos@dev88.test', roles: ['ROLE_ADMIN', 'ROLE_USER']);

        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        // No duplicates: getRoles() is already deduped and re-indexed.
        self::assertSame($user->getRoles(), array_values(array_unique($user->getRoles())));
    }

    public function testUserIdentifierIsTheEmail(): void
    {
        $user = new User(email: 'magos@dev88.test');

        self::assertSame('magos@dev88.test', $user->getUserIdentifier());
    }

    public function testIsEqualToMatchesOnIdentityAndRoles(): void
    {
        $user = new User(email: 'magos@dev88.test');

        self::assertTrue($user->isEqualTo($user));
        self::assertFalse($user->isEqualTo(new User(email: 'other@dev88.test')));
    }

    public function testSyncPrimaryEmailCacheAdoptsThePrimaryRowsAddress(): void
    {
        // A User starts with its constructor-created primary; a primary swap unmarks it and promotes a
        // newly-verified address. The denormalized cache should then follow the new primary.
        $user = new User(email: 'old@dev88.test');
        $originalPrimary = $user->emails->first();
        self::assertInstanceOf(UserEmail::class, $originalPrimary);
        $originalPrimary->unmarkPrimary();

        $newPrimary = new UserEmail(user: $user, email: 'new@dev88.test', isPrimary: false, verifiedAt: new \DateTimeImmutable());
        $newPrimary->markPrimary();

        $user->syncPrimaryEmailCache();

        self::assertSame('new@dev88.test', $user->email);
    }
}
