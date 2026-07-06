<?php

// ABOUTME: Doctrine repository for PasskeyCredential - bridges Obol's row to the WebAuthn bundle's CredentialRecord shape.
// ABOUTME: Implements PublicKeyCredentialSourceRepositoryInterface (legacy) + CanSaveCredentialRecord (v5.3); the bundle aliases both to this service.

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PasskeyCredential;
use App\Entity\User;
use App\Security\PasskeyRegistrationSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Webauthn\Bundle\Repository\CanSaveCredentialRecord;
use Webauthn\Bundle\Repository\PublicKeyCredentialSourceRepositoryInterface;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * The bundle's compile pass aliases BOTH the v5.3 CredentialRecordRepositoryInterface AND the legacy
 * PublicKeyCredentialSourceRepositoryInterface to the service listed under webauthn.credential_repository.
 * Declaring the legacy interface here covers both alias targets (it extends the new one without adding
 * methods) and keeps lint:container's CheckAliasValidityPass happy.
 *
 * @extends ServiceEntityRepository<PasskeyCredential>
 */
class PasskeyCredentialRepository extends ServiceEntityRepository implements PublicKeyCredentialSourceRepositoryInterface, CanSaveCredentialRecord
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly Security $security,
        private readonly UserRepository $userRepository,
        private readonly RequestStack $requestStack,
    ) {
        parent::__construct($registry, PasskeyCredential::class);
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?CredentialRecord
    {
        return $this->findEntityByCredentialId($publicKeyCredentialId)?->toCredentialRecord();
    }

    /**
     * @return array<CredentialRecord>
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $userHandle = self::userHandleFromBundleBytes($publicKeyCredentialUserEntity->id);

        $rows = $this->createQueryBuilder('pc')
            ->where('pc.userHandle = :handle')
            ->setParameter('handle', $userHandle, 'uuid')
            ->getQuery()
            ->getResult()
        ;

        return array_map(static fn (PasskeyCredential $row): CredentialRecord => $row->toCredentialRecord(), $rows);
    }

    /**
     * Bundle write path - called on registration *and* on each successful assertion.
     *
     * - Existing row (assertion path): bump counter + lastUsedAt.
     * - Missing row (registration path): create with a default name `Passkey #N` (N = the user's
     *   existing credential count + 1). The user may rename it afterwards. The acting User comes from
     *   the security context - the bundle's ceremony routes are inside the authenticated firewall.
     */
    public function saveCredentialRecord(CredentialRecord $credentialRecord): void
    {
        $entity = $this->findEntityByCredentialId($credentialRecord->publicKeyCredentialId);

        if ($entity instanceof PasskeyCredential) {
            $entity->recordAssertion($credentialRecord->counter);
            $this->getEntityManager()->flush();

            return;
        }

        $actor = $this->security->getUser();
        if (!$actor instanceof User) {
            throw new \RuntimeException('Cannot save a new passkey credential without an authenticated user.');
        }

        $owner = $this->resolveOwner($credentialRecord, $actor);
        $userAgent = $this->requestStack->getCurrentRequest()?->headers->get('User-Agent');
        if (\is_string($userAgent) && \strlen($userAgent) > 255) {
            $userAgent = substr($userAgent, 0, 255);
        }

        $count = \count($this->findForUser($owner));
        $name = \sprintf('Passkey #%d', $count + 1);

        $created = new PasskeyCredential(
            record: $credentialRecord,
            user: $owner,
            name: $name,
            userAgentAtRegistration: $userAgent,
        );

        $this->persist($created);
        $this->getEntityManager()->flush();

        $session = $this->requestStack->getCurrentRequest()?->getSession();
        $session?->set(PasskeyRegistrationSession::JUST_REGISTERED_KEY, (string) $created->id);
    }

    public function persist(PasskeyCredential $credential): void
    {
        $this->getEntityManager()->persist($credential);
    }

    public function remove(PasskeyCredential $credential): void
    {
        $this->getEntityManager()->remove($credential);
    }

    /**
     * @return list<PasskeyCredential>
     */
    public function findForUser(User $owner): array
    {
        return $this->createQueryBuilder('pc')
            ->where('pc.user = :owner')
            ->setParameter('owner', $owner->id, 'ulid')
            ->orderBy('pc.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return list<PasskeyCredential>
     */
    public function findForOwnerId(Ulid $ownerUserId): array
    {
        return $this->createQueryBuilder('pc')
            ->where('pc.user = :owner')
            ->setParameter('owner', $ownerUserId, 'ulid')
            ->orderBy('pc.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * The credential with this id, but only when it belongs to `$ownerUserId`. Returns null otherwise -
     * the owner-scoped read behind the rename / revoke controllers, which 404 on null.
     */
    public function findForOwner(Ulid $id, Ulid $ownerUserId): ?PasskeyCredential
    {
        $result = $this->createQueryBuilder('pc')
            ->where('pc.id = :id')
            ->andWhere('pc.user = :owner')
            ->setParameter('id', $id, 'ulid')
            ->setParameter('owner', $ownerUserId, 'ulid')
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof PasskeyCredential ? $result : null;
    }

    public function findEntityByCredentialId(string $publicKeyCredentialId): ?PasskeyCredential
    {
        return $this->findOneBy(['publicKeyCredentialId' => $publicKeyCredentialId]);
    }

    /**
     * The CredentialRecord carries a binary userHandle. Resolve it to the matching User - usually the
     * same as the security-context user, but compare defensively in case the bundle swapped contexts.
     */
    private function resolveOwner(CredentialRecord $credentialRecord, User $actor): User
    {
        if ($actor->userHandle->toBinary() === $credentialRecord->userHandle) {
            return $actor;
        }

        if (16 !== \strlen($credentialRecord->userHandle)) {
            throw new \RuntimeException('CredentialRecord.userHandle is not the expected 16-byte form.');
        }

        $owner = $this->userRepository->findOneBy([
            'userHandle' => Uuid::fromBinary($credentialRecord->userHandle),
        ]);
        if (!$owner instanceof User) {
            throw new \RuntimeException('No User row matches the CredentialRecord.userHandle.');
        }

        return $owner;
    }

    /**
     * The bundle stores PublicKeyCredentialUserEntity.id as the raw binary userHandle. Convert it back
     * to a Uuid so the Doctrine query binds the column correctly. Falls back to RFC4122 parsing for
     * callers (tests) that pass the human-readable form.
     */
    private static function userHandleFromBundleBytes(string $idBytes): Uuid
    {
        if (16 === \strlen($idBytes)) {
            return Uuid::fromBinary($idBytes);
        }

        return Uuid::fromString($idBytes);
    }
}
