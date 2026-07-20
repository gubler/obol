<?php

// ABOUTME: Unit tests for the User entity - role handling, identity, equality, and primary-email cache sync.
// ABOUTME: User is the passwordless account; UserEmail rows carry the addresses (see UserEmailTest).

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Entity\UserEmail;
use App\Enum\Currency;
use App\Enum\DateFormat;
use App\Enum\SavingsDisplay;
use App\Tests\Support\PinsDefaultTimezone;
use App\ValueObject\CalendarDate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class UserTest extends TestCase
{
    use PinsDefaultTimezone;

    public function testDefaultsToUsdCurrencyUnresolvedLocaleAppTimezoneAndMediumDates(): void
    {
        $user = new User(email: 'magos@dev88.test');

        self::assertSame(Currency::USD, $user->displayCurrency);
        // Locale is unresolved until the browser guess (or the user) sets it.
        self::assertNull($user->locale);
        self::assertSame('America/New_York', $user->timezone);
        self::assertSame(DateFormat::Medium, $user->dateFormat);
    }

    public function testDefaultsToMonthOfSavingsDisplay(): void
    {
        // New accounts save by the due month, the way most people budget (see ADR-0009).
        $user = new User(email: 'magos@dev88.test');

        self::assertSame(SavingsDisplay::MonthOf, $user->savingsDisplay);
    }

    public function testResolveLocaleSetsTheLocale(): void
    {
        $user = new User(email: 'magos@dev88.test');

        $user->resolveLocale('de-DE');

        self::assertSame('de-DE', $user->locale);
    }

    public function testResolveLocaleIsRejectedOnceAlreadyResolved(): void
    {
        // Strictly the one-time initial resolution: it must never silently overwrite an existing locale.
        $user = new User(email: 'magos@dev88.test', locale: 'en-US');

        $this->expectException(\InvalidArgumentException::class);
        $user->resolveLocale('de-DE');
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

    public function testDisplayNameDefaultsToTheEmailAndIsNotYetOnboarded(): void
    {
        // A freshly-created account has never confirmed its first-run settings, and is never nameless:
        // displayName seeds to the email until onboarding replaces it.
        $user = new User(email: 'magos@dev88.test');

        self::assertSame('magos@dev88.test', $user->displayName);
        self::assertFalse($user->hasCompletedOnboarding());
        self::assertNull($user->onboardingCompletedAt);
    }

    public function testCompleteOnboardingConfirmsSettingsStampsAndReplacesTheName(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $at = new \DateTimeImmutable('2026-07-06 12:00:00');

        $user->completeOnboarding('Magos', Currency::GBP, 'Europe/London', $at);

        self::assertSame('Magos', $user->displayName);
        self::assertSame(Currency::GBP, $user->displayCurrency);
        self::assertSame('Europe/London', $user->timezone);
        self::assertTrue($user->hasCompletedOnboarding());
        self::assertSame($at, $user->onboardingCompletedAt);
    }

    public function testCompleteOnboardingWithABlankNameKeepsTheEmailDefault(): void
    {
        $user = new User(email: 'magos@dev88.test');

        $user->completeOnboarding('   ', Currency::EUR, 'Europe/Berlin');

        // The name is optional; leaving it blank keeps the never-nameless email default.
        self::assertSame('magos@dev88.test', $user->displayName);
        self::assertSame(Currency::EUR, $user->displayCurrency);
        self::assertTrue($user->hasCompletedOnboarding());
    }

    public function testCompleteOnboardingIsRejectedOnceAlreadyComplete(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $user->completeOnboarding('Magos', Currency::GBP, 'Europe/London');

        $this->expectException(\InvalidArgumentException::class);
        $user->completeOnboarding('Someone Else', Currency::USD, 'America/New_York');
    }

    public function testChangeDisplayNameReplacesTheName(): void
    {
        $user = new User(email: 'magos@dev88.test');

        $user->changeDisplayName('  Magos  ');

        // Trimmed, and applied even after onboarding (unlike completeOnboarding, which is one-shot).
        self::assertSame('Magos', $user->displayName);
    }

    public function testChangeDisplayNameToBlankRevertsToTheEmailDefault(): void
    {
        // Clearing the name is a real intent: fall back to the never-nameless email seed.
        $user = new User(email: 'magos@dev88.test');
        $user->changeDisplayName('Magos');

        $user->changeDisplayName('   ');

        self::assertSame('magos@dev88.test', $user->displayName);
    }

    public function testChangePreferencesUpdatesCurrencyTimezoneLocaleAndDateFormat(): void
    {
        $user = new User(email: 'magos@dev88.test');

        $user->changePreferences(Currency::GBP, 'Europe/London', 'en-GB', DateFormat::Short, SavingsDisplay::MonthBefore);

        self::assertSame(Currency::GBP, $user->displayCurrency);
        self::assertSame('Europe/London', $user->timezone);
        self::assertSame('en-GB', $user->locale);
        self::assertSame(DateFormat::Short, $user->dateFormat);
        self::assertSame(SavingsDisplay::MonthBefore, $user->savingsDisplay);
    }

    public function testChangePreferencesOverwritesAnAlreadyResolvedLocale(): void
    {
        // Unlike resolveLocale (one-shot inference), the settings picker deliberately changes an
        // existing locale - that is exactly its job.
        $user = new User(email: 'magos@dev88.test', locale: 'en-US');

        $user->changePreferences(Currency::USD, 'America/New_York', 'en-CA', DateFormat::Medium, SavingsDisplay::MonthOf);

        self::assertSame('en-CA', $user->locale);
    }

    public function testAlwaysCarriesRoleUserAndDedupesExplicitRoles(): void
    {
        $user = new User(email: 'magos@dev88.test', roles: ['ROLE_ADMIN', 'ROLE_USER']);

        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertContains('ROLE_ADMIN', $user->getRoles());
        // No duplicates: getRoles() is already deduped and re-indexed.
        self::assertSame($user->getRoles(), array_values(array_unique($user->getRoles())));
    }

    public function testIsAdminReflectsTheAdminRole(): void
    {
        self::assertFalse(new User(email: 'magos@dev88.test')->isAdmin());
        self::assertTrue(new User(email: 'magos@dev88.test', roles: ['ROLE_ADMIN'])->isAdmin());
    }

    public function testGrantAdminAddsTheOperatorRole(): void
    {
        $user = new User(email: 'magos@dev88.test');

        $user->grantAdmin();

        self::assertContains('ROLE_ADMIN', $user->getRoles());
    }

    public function testGrantAdminIsIdempotent(): void
    {
        $user = new User(email: 'magos@dev88.test', roles: ['ROLE_ADMIN']);

        $user->grantAdmin();

        // No duplicate is appended to the stored roles array.
        self::assertSame(['ROLE_ADMIN'], $user->roles);
    }

    public function testRevokeAdminRemovesTheOperatorRole(): void
    {
        $user = new User(email: 'magos@dev88.test', roles: ['ROLE_ADMIN']);

        $user->revokeAdmin();

        self::assertNotContains('ROLE_ADMIN', $user->getRoles());
        self::assertSame([], $user->roles);
    }

    public function testRevokeAdminOnANonAdminIsANoOp(): void
    {
        $user = new User(email: 'magos@dev88.test');

        $user->revokeAdmin();

        self::assertSame([], $user->roles);
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

    public function testCarriesAStableWebAuthnUserHandleGeneratedAtConstruction(): void
    {
        // The userHandle is the stable, opaque identifier passkeys bind to: distinct from the email
        // (which can change) and distinct per user. Generated in the constructor so a User is never
        // without one.
        $user = new User(email: 'magos@dev88.test');
        $other = new User(email: 'other@dev88.test');

        self::assertInstanceOf(Uuid::class, $user->userHandle);
        self::assertFalse($user->userHandle->equals($other->userHandle));
    }

    public function testLocalDateForReadsTheSameInstantAsADifferentCalendarDayInEachOwnersZone(): void
    {
        // One instant, read as a calendar date in four zones spanning UTC: the day differs by zone.
        $instant = new \DateTimeImmutable('2026-08-01 06:00:00', new \DateTimeZone('UTC'));

        self::assertTrue(
            CalendarDate::for(2026, 7, 31)->equals(
                new User(email: 'hi@dev88.test', timezone: 'Pacific/Honolulu')->localDateFor($instant),
            ),
            'Honolulu (UTC-10) is still Jul 31 at 06:00 UTC',
        );
        self::assertTrue(
            CalendarDate::for(2026, 8, 1)->equals(
                new User(email: 'utc@dev88.test', timezone: 'UTC')->localDateFor($instant),
            ),
        );
        self::assertTrue(
            CalendarDate::for(2026, 8, 1)->equals(
                new User(email: 'jp@dev88.test', timezone: 'Asia/Tokyo')->localDateFor($instant),
            ),
        );
    }

    public function testLocalDateForResolvesTheLiveBugInstantToTheOwnersPreviousDay(): void
    {
        // The bug this whole refactor fixes: 03:59:59 UTC on Aug 1 is still Jul 31 for a New Yorker
        // (UTC-4 EDT), so a bill due Aug 1 must not be counted in July.
        $newYorker = new User(email: 'ny@dev88.test', timezone: 'America/New_York');
        $instant = new \DateTimeImmutable('2026-08-01 03:59:59', new \DateTimeZone('UTC'));

        self::assertTrue(CalendarDate::for(2026, 7, 31)->equals($newYorker->localDateFor($instant)));
    }

    public function testLocalDateForFollowsAChangeOfTimezone(): void
    {
        // ADR-0016's claim, made testable: the owner's zone is applied at read time, so re-reading the
        // same instant after a timezone change yields a different calendar date - nothing is stored.
        $user = new User(email: 'mover@dev88.test', timezone: 'Asia/Tokyo');
        $instant = new \DateTimeImmutable('2026-07-31 20:00:00', new \DateTimeZone('UTC'));

        self::assertTrue(CalendarDate::for(2026, 8, 1)->equals($user->localDateFor($instant)));

        $user->changePreferences(
            displayCurrency: Currency::USD,
            timezone: 'Pacific/Honolulu',
            locale: 'en',
            dateFormat: DateFormat::Medium,
            savingsDisplay: SavingsDisplay::MonthOf,
        );

        self::assertTrue(CalendarDate::for(2026, 7, 31)->equals($user->localDateFor($instant)));
    }

    public function testLocalDateForIsIndependentOfTheAmbientProcessTimezone(): void
    {
        $newYorker = new User(email: 'ny@dev88.test', timezone: 'America/New_York');
        $instant = new \DateTimeImmutable('2026-08-01 03:59:59', new \DateTimeZone('UTC'));

        date_default_timezone_set('Pacific/Kiritimati');
        $ahead = $newYorker->localDateFor($instant);
        date_default_timezone_set('Pacific/Midway');
        $behind = $newYorker->localDateFor($instant);

        self::assertTrue($ahead->equals($behind));
        self::assertTrue(CalendarDate::for(2026, 7, 31)->equals($ahead));
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
