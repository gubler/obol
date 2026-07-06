<?php

// ABOUTME: Unit tests for RevokePasskeyHandler - removes an owner-scoped passkey, throws when it is absent.
// ABOUTME: Mocks the repository; asserts the owner-scoped lookup drives find/throw/remove.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\PasskeyCredential;

use App\Entity\PasskeyCredential;
use App\Entity\User;
use App\Message\Command\PasskeyCredential\RevokePasskeyCommand;
use App\Message\Command\PasskeyCredential\RevokePasskeyHandler;
use App\Repository\PasskeyCredentialRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

final class RevokePasskeyHandlerTest extends TestCase
{
    public function testRemovesTheOwnerScopedCredential(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $credential = self::credential($user);
        $ownerId = $user->id;
        $credentialId = $credential->id;

        $repository = $this->createMock(PasskeyCredentialRepository::class);
        $repository->expects(self::once())->method('findForOwner')
            ->with($credentialId, $ownerId)
            ->willReturn($credential)
        ;
        $repository->expects(self::once())->method('remove')->with($credential);

        $handler = new RevokePasskeyHandler($repository);
        $handler(new RevokePasskeyCommand(credentialId: $credentialId, ownerUserId: $ownerId));
    }

    public function testThrowsWhenTheCredentialIsAbsentOrCrossOwner(): void
    {
        $repository = $this->createMock(PasskeyCredentialRepository::class);
        $repository->method('findForOwner')->willReturn(null);
        $repository->expects(self::never())->method('remove');

        $handler = new RevokePasskeyHandler($repository);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new RevokePasskeyCommand(credentialId: new Ulid(), ownerUserId: new Ulid()));
    }

    private static function credential(User $user): PasskeyCredential
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

        return new PasskeyCredential($record, $user, name: 'Phone');
    }
}
