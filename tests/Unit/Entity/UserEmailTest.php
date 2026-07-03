<?php

// ABOUTME: Unit tests for the UserEmail entity - the per-(user,email) row with verified + primary flags.
// ABOUTME: Enforces the invariant that a primary address must be verified, and the verify/promote transitions.

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Entity\UserEmail;
use PHPUnit\Framework\TestCase;

final class UserEmailTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new User(email: 'magos@dev88.test');
    }

    public function testTheConstructorCreatedPrimaryIsVerifiedAndRegistered(): void
    {
        // The User constructor creates the primary UserEmail; it is the address the user was built with.
        $primary = $this->user->emails->first();

        self::assertInstanceOf(UserEmail::class, $primary);
        self::assertTrue($primary->isPrimary);
        self::assertTrue($primary->isVerified());
        self::assertSame('magos@dev88.test', $primary->email);
    }

    public function testAnUnverifiedSecondaryIsValid(): void
    {
        $email = new UserEmail(user: $this->user, email: 'second@dev88.test', isPrimary: false, verifiedAt: null);

        self::assertFalse($email->isPrimary);
        self::assertFalse($email->isVerified());
    }

    public function testAPrimaryAddressMustBeVerified(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UserEmail(user: $this->user, email: 'magos@dev88.test', isPrimary: true, verifiedAt: null);
    }

    public function testMarkVerifiedStampsTheAddress(): void
    {
        $email = new UserEmail(user: $this->user, email: 'second@dev88.test', isPrimary: false, verifiedAt: null);

        $email->markVerified();

        self::assertTrue($email->isVerified());
    }

    public function testMarkPrimaryRequiresVerificationThenFlips(): void
    {
        $email = new UserEmail(user: $this->user, email: 'second@dev88.test', isPrimary: false, verifiedAt: null);

        try {
            $email->markPrimary();
            self::fail('Expected marking an unverified address primary to throw.');
        } catch (\InvalidArgumentException) {
            // expected
        }

        $email->markVerified();
        $email->markPrimary();

        self::assertTrue($email->isPrimary);

        $email->unmarkPrimary();

        self::assertFalse($email->isPrimary);
    }
}
