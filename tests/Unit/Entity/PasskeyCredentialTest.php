<?php

// ABOUTME: Unit tests for the PasskeyCredential entity - construction from a WebAuthn CredentialRecord, name
// ABOUTME: invariants, the toCredentialRecord() round-trip, and the recordAssertion()/rename() domain methods.

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\PasskeyCredential;
use App\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

final class PasskeyCredentialTest extends TestCase
{
    public function testIsBuiltFromACredentialRecordAndCopiesTheOwnersUserHandle(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $record = self::record($user, counter: 7);

        $credential = new PasskeyCredential($record, $user, name: 'Yubikey', userAgentAtRegistration: 'UA/1.0');

        self::assertSame($user, $credential->user);
        self::assertTrue($credential->userHandle->equals($user->userHandle));
        self::assertSame('Yubikey', $credential->name);
        self::assertSame(7, $credential->counter);
        self::assertSame('UA/1.0', $credential->userAgentAtRegistration);
        self::assertNull($credential->lastUsedAt);
    }

    public function testTrimsAndRejectsAnEmptyName(): void
    {
        $user = new User(email: 'magos@dev88.test');

        $this->expectException(\InvalidArgumentException::class);
        new PasskeyCredential(self::record($user), $user, name: '   ');
    }

    public function testRejectsANameLongerThanTheMaximum(): void
    {
        $user = new User(email: 'magos@dev88.test');

        $this->expectException(\InvalidArgumentException::class);
        new PasskeyCredential(self::record($user), $user, name: str_repeat('a', PasskeyCredential::NAME_MAX_LENGTH + 1));
    }

    public function testToCredentialRecordRoundTripsTheStoredShape(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $record = self::record($user, counter: 3);

        $credential = new PasskeyCredential($record, $user, name: 'Phone');
        $roundTripped = $credential->toCredentialRecord();

        self::assertSame($record->publicKeyCredentialId, $roundTripped->publicKeyCredentialId);
        self::assertSame($record->counter, $roundTripped->counter);
        self::assertSame($user->userHandle->toBinary(), $roundTripped->userHandle);
    }

    public function testRecordAssertionAdvancesTheCounterAndStampsLastUsed(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $credential = new PasskeyCredential(self::record($user, counter: 1), $user, name: 'Phone');
        $at = new \DateTimeImmutable('2026-07-05 12:00:00');

        $credential->recordAssertion(counter: 9, at: $at);

        self::assertSame(9, $credential->counter);
        self::assertSame($at, $credential->lastUsedAt);
    }

    public function testRenameChangesTheNameAndReportsWhetherItChanged(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $credential = new PasskeyCredential(self::record($user), $user, name: 'Old');

        self::assertTrue($credential->rename('New'));
        self::assertSame('New', $credential->name);
        // Idempotent: renaming to the current name is a no-op that reports no change.
        self::assertFalse($credential->rename('New'));
    }

    public function testRenameRejectsAnEmptyName(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $credential = new PasskeyCredential(self::record($user), $user, name: 'Old');

        $this->expectException(\InvalidArgumentException::class);
        $credential->rename('  ');
    }

    private static function record(User $user, int $counter = 0): CredentialRecord
    {
        return new CredentialRecord(
            publicKeyCredentialId: random_bytes(16),
            type: 'public-key',
            transports: ['internal'],
            attestationType: 'none',
            trustPath: new EmptyTrustPath(),
            aaguid: Uuid::v4(),
            credentialPublicKey: random_bytes(32),
            userHandle: $user->userHandle->toBinary(),
            counter: $counter,
        );
    }
}
