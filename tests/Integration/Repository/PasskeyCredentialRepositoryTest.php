<?php

// ABOUTME: Integration test for PasskeyCredential persistence + owner-scoped finders against a real database.
// ABOUTME: Guards the DBAL type wiring (base64/aaguid/trust_path) and the userHandle round-trip.

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\PasskeyCredential;
use App\Entity\User;
use App\Repository\PasskeyCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

final class PasskeyCredentialRepositoryTest extends KernelTestCase
{
    public function testPersistsAndReadsBackACredentialScopedToItsOwner(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var PasskeyCredentialRepository $repository */
        $repository = self::getContainer()->get(PasskeyCredentialRepository::class);

        $owner = new User(email: 'owner@dev88.test');
        $other = new User(email: 'other@dev88.test');
        $em->persist($owner);
        $em->persist($other);

        $credential = new PasskeyCredential(self::record($owner), $owner, name: 'My Phone');
        $em->persist($credential);
        $em->flush();
        $em->clear();

        $forOwner = $repository->findForOwnerId($owner->id);
        self::assertCount(1, $forOwner);
        self::assertSame('My Phone', $forOwner[0]->name);
        self::assertTrue($forOwner[0]->userHandle->equals($owner->userHandle));

        // Owner isolation: the other user sees nothing, and the scoped single lookup is null cross-owner.
        self::assertCount(0, $repository->findForOwnerId($other->id));
        self::assertNull($repository->findForOwner($credential->id, $other->id));
        self::assertNotNull($repository->findForOwner($credential->id, $owner->id));
    }

    private static function record(User $user): CredentialRecord
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
            counter: 0,
        );
    }
}
