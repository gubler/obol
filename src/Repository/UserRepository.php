<?php

// ABOUTME: Doctrine repository for User accounts.
// ABOUTME: Also backs the WebAuthn bundle via the PublicKeyCredentialUserEntity interfaces (passkey login + registration).

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserEmail;
use App\Exception\CannotRemoveLastAdminException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * One page of accounts matching a search term, ordered by email, for the admin user list. A deliberate
     * cross-owner read: unlike the owner-scoped finders, it is not filtered to one user (see ADR-0015).
     * Search matches the display name or any of a user's email addresses; a blank term matches everyone.
     *
     * @return list<User>
     */
    public function findMatching(string $search, int $limit, int $offset): array
    {
        return $this->matchingQueryBuilder($search)
            ->orderBy('u.email', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * How many accounts match the search term (the same predicate as findMatching), for paging.
     */
    public function countMatching(string $search): int
    {
        $count = $this->matchingQueryBuilder($search)
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult()
        ;

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * The shared search predicate: match on the display name or on any of the user's email addresses.
     * Email is a citext column (case-insensitive); the display name is lowered to match the same way.
     */
    private function matchingQueryBuilder(string $search): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u');

        $term = trim($search);
        if ('' !== $term) {
            $qb
                ->andWhere('LOWER(u.displayName) LIKE :q OR EXISTS (SELECT 1 FROM ' . UserEmail::class . ' ue WHERE ue.user = u AND ue.email LIKE :q)')
                ->setParameter('q', '%' . mb_strtolower($term) . '%')
            ;
        }

        return $qb;
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
     * Resolve an account by its canonical primary email (the denormalized `email` column, which is citext
     * so the match is case-insensitive). Used by the admin promote flow, which addresses a user by email.
     */
    public function findForEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Every account that currently holds ROLE_ADMIN. The admin population is tiny, so this filters in
     * PHP rather than reaching for a JSON-containment query; `isAdmin()` keeps the role check in one place.
     *
     * @return list<User>
     */
    public function findAdmins(): array
    {
        return array_values(array_filter($this->findAll(), static fn (User $user): bool => $user->isAdmin()));
    }

    public function countAdmins(): int
    {
        return \count($this->findAdmins());
    }

    /**
     * Guard the system invariant that at least one admin always remains: refuse to de-admin the given
     * user when they are the only admin, so the operator surface can never be locked out. A no-op for a
     * non-admin. Enforced here at the data layer so every caller - the console now, the web UI later -
     * inherits it. See ADR-0019.
     */
    public function assertNotLastAdmin(User $user): void
    {
        if ($user->isAdmin() && $this->countAdmins() <= 1) {
            throw new CannotRemoveLastAdminException($user->email);
        }
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
