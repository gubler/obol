<?php

// ABOUTME: Unit tests for the passkey query runners - list-for-user and owner-scoped single lookup.
// ABOUTME: Mocks the repository; asserts each runner delegates to the matching owner-scoped finder.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query\PasskeyCredential;

use App\Entity\PasskeyCredential;
use App\Entity\User;
use App\Message\Query\PasskeyCredential\FindPasskeyForOwnerQuery;
use App\Message\Query\PasskeyCredential\FindPasskeyForOwnerRunner;
use App\Message\Query\PasskeyCredential\FindPasskeysForUserQuery;
use App\Message\Query\PasskeyCredential\FindPasskeysForUserRunner;
use App\Repository\PasskeyCredentialRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

final class PasskeyQueryRunnersTest extends TestCase
{
    public function testFindPasskeysForUserReturnsTheOwnersCredentials(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $credentials = [self::credential($user)];

        $repository = $this->createMock(PasskeyCredentialRepository::class);
        $repository->expects(self::once())->method('findForOwnerId')
            ->with($user->id)
            ->willReturn($credentials)
        ;

        $runner = new FindPasskeysForUserRunner($repository);

        self::assertSame($credentials, $runner(new FindPasskeysForUserQuery(ownerUserId: $user->id)));
    }

    public function testFindPasskeyForOwnerReturnsTheScopedCredentialOrNull(): void
    {
        $user = new User(email: 'magos@dev88.test');
        $credential = self::credential($user);

        $repository = $this->createMock(PasskeyCredentialRepository::class);
        $repository->expects(self::once())->method('findForOwner')
            ->with($credential->id, $user->id)
            ->willReturn($credential)
        ;

        $runner = new FindPasskeyForOwnerRunner($repository);

        self::assertSame($credential, $runner(new FindPasskeyForOwnerQuery(credentialId: $credential->id, ownerUserId: $user->id)));
    }

    public function testFindPasskeyForOwnerReturnsNullCrossOwner(): void
    {
        $repository = $this->createMock(PasskeyCredentialRepository::class);
        $repository->expects(self::once())->method('findForOwner')->willReturn(null);

        $runner = new FindPasskeyForOwnerRunner($repository);

        self::assertNull($runner(new FindPasskeyForOwnerQuery(credentialId: new Ulid(), ownerUserId: new Ulid())));
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
