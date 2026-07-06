<?php

// ABOUTME: Unit tests for RenamePasskeyHandler - renames an owner-scoped passkey, reports whether it changed.
// ABOUTME: Mocks the repository; asserts the owner-scoped lookup drives find/throw/rename.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\PasskeyCredential;

use App\Entity\PasskeyCredential;
use App\Entity\User;
use App\Message\Command\PasskeyCredential\RenamePasskeyCommand;
use App\Message\Command\PasskeyCredential\RenamePasskeyHandler;
use App\Repository\PasskeyCredentialRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

final class RenamePasskeyHandlerTest extends TestCase
{
    public function testRenamesAndReportsAChange(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $credential = self::credential($user, name: 'Old');

        $repository = $this->createMock(PasskeyCredentialRepository::class);
        $repository->expects(self::once())->method('findForOwner')->willReturn($credential);

        $handler = new RenamePasskeyHandler($repository);
        $changed = $handler(new RenamePasskeyCommand(credentialId: $credential->id, ownerUserId: $user->id, name: 'New'));

        self::assertTrue($changed);
        self::assertSame('New', $credential->name);
    }

    public function testReportsNoChangeWhenTheNameIsUnchanged(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $credential = self::credential($user, name: 'Same');

        $repository = $this->createMock(PasskeyCredentialRepository::class);
        $repository->expects(self::once())->method('findForOwner')->willReturn($credential);

        $handler = new RenamePasskeyHandler($repository);

        self::assertFalse($handler(new RenamePasskeyCommand(credentialId: $credential->id, ownerUserId: $user->id, name: 'Same')));
    }

    public function testThrowsWhenTheCredentialIsAbsentOrCrossOwner(): void
    {
        $repository = $this->createMock(PasskeyCredentialRepository::class);
        $repository->expects(self::once())->method('findForOwner')->willReturn(null);

        $handler = new RenamePasskeyHandler($repository);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new RenamePasskeyCommand(credentialId: new Ulid(), ownerUserId: new Ulid(), name: 'Anything'));
    }

    private static function credential(User $user, string $name): PasskeyCredential
    {
        $record = new CredentialRecord(
            publicKeyCredentialId: random_bytes(16),
            type: 'public-key',
            transports: ['internal'],
            attestationType: 'none',
            trustPath: new EmptyTrustPath(),
            aaguid: Uuid::v4(),
            credentialPublicKey: random_bytes(32),
            userHandle: $user->userHandle->toBinary(),
            counter: 0,
        );

        return new PasskeyCredential($record, $user, name: $name);
    }
}
