<?php

// ABOUTME: Doctrine repository for User accounts.
// ABOUTME: Also backs the WebAuthn bundle via the PublicKeyCredentialUserEntity interfaces (passkey login + registration).

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserEmail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Webauthn\Bundle\Repository\CanGenerateUserEntity;
use Webauthn\Bundle\Repository\CanRegisterUserEntity;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PublicKeyCredentialUserEntityRepositoryInterface, CanRegisterUserEntity, CanGenerateUserEntity
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly UserEmailRepository $userEmails,
    ) {
        parent::__construct($registry, User::class);
    }

    /**
     * Load a user by id, throwing when it is absent. Handlers that resolve an ownerUserId carried on a
     * message use this: the owner is a foreign key that must exist, so a missing row is a bug, not a 404.
     */
    public function getForId(Ulid $id): User
    {
        $user = $this->find($id);

        if (null === $user) {
            throw new \InvalidArgumentException(\sprintf('User with ID "%s" not found.', $id));
        }

        return $user;
    }

    /**
     * WebAuthn bundle hook - resolves a "username" (we treat it as an email, per the
     * autocomplete="username webauthn" wiring on /login) to a wrapper carrying the User's stable opaque
     * userHandle. Reuses the verified-email lookup so a verified secondary works for passkey login too.
     */
    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        $row = $this->userEmails->findVerifiedByEmail($username);

        return $row instanceof UserEmail ? self::toBundleUserEntity($row->user) : null;
    }

    /**
     * WebAuthn bundle hook - handed the raw 16-byte userHandle from the authenticator and asked to
     * resolve it to a wrapper. Convert binary to Uuid for the Doctrine query.
     */
    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        if (16 !== \strlen($userHandle)) {
            return null;
        }

        $user = $this->findOneBy(['userHandle' => Uuid::fromBinary($userHandle)]);

        return $user instanceof User ? self::toBundleUserEntity($user) : null;
    }

    /**
     * WebAuthn bundle hook for the registration ceremony, which only ever runs against a logged-in user.
     * We never create a brand-new User here (accounts are created via app:user:create / signup); we look
     * the existing verified email up and return the same wrapper as findOneByUsername.
     */
    public function generateUserEntity(?string $username, ?string $displayName): PublicKeyCredentialUserEntity
    {
        if (null === $username || '' === $username) {
            throw new \RuntimeException('generateUserEntity called without a username; passkey registration runs against an authenticated User.');
        }

        $row = $this->userEmails->findVerifiedByEmail($username);

        if (!$row instanceof UserEmail) {
            throw new \RuntimeException(\sprintf('No verified UserEmail row for "%s" - cannot generate user entity.', $username));
        }

        return self::toBundleUserEntity($row->user);
    }

    /**
     * No-op: User rows are created via the console command / signup flow, not the WebAuthn bundle. The
     * interface exists for bundles that auto-create accounts on first passkey registration - not our flow.
     */
    public function saveUserEntity(PublicKeyCredentialUserEntity $userEntity): void
    {
    }

    private static function toBundleUserEntity(User $user): PublicKeyCredentialUserEntity
    {
        return new PublicKeyCredentialUserEntity(
            name: $user->email,
            id: $user->userHandle->toBinary(),
            displayName: $user->email,
        );
    }
}
