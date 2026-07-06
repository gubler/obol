<?php

// ABOUTME: Doctrine repository for UserEmail rows; resolves a verified address to its row for the firewall.
// ABOUTME: Also backs the /account/emails management surface (owner-scoped reads + the primary lookup).

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserEmail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<UserEmail>
 */
class UserEmailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserEmail::class);
    }

    /**
     * The verified row for an address, or null when the address is unknown or unverified. Case-insensitive
     * via the citext column. Unknown and unverified are indistinguishable to callers - the firewall relies
     * on that for enumeration safety.
     */
    public function findVerifiedByEmail(string $email): ?UserEmail
    {
        $result = $this->createQueryBuilder('ue')
            ->andWhere('ue.email = :email')
            ->andWhere('ue.verifiedAt IS NOT NULL')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof UserEmail ? $result : null;
    }

    /**
     * Every address on this user's account, ordered for the management page: the primary first, then
     * verified secondaries, then still-pending ones; oldest first within each group.
     *
     * @return list<UserEmail>
     */
    public function findForOwnerId(Ulid $ownerUserId): array
    {
        return $this->createQueryBuilder('ue')
            ->where('ue.user = :owner')
            ->setParameter('owner', $ownerUserId, 'ulid')
            ->addOrderBy('ue.isPrimary', 'DESC')
            ->addOrderBy('CASE WHEN ue.verifiedAt IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('ue.createdAt', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * The row with this id, but only when it belongs to `$ownerUserId`. Returns null otherwise - the
     * owner-scoped read behind the promote / remove / resend controllers, which 404 on null.
     */
    public function findForOwner(Ulid $id, Ulid $ownerUserId): ?UserEmail
    {
        $result = $this->createQueryBuilder('ue')
            ->where('ue.id = :id')
            ->andWhere('ue.user = :owner')
            ->setParameter('id', $id, 'ulid')
            ->setParameter('owner', $ownerUserId, 'ulid')
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof UserEmail ? $result : null;
    }

    /**
     * The user's current primary row. A User always has exactly one (enforced by the constructor and the
     * partial unique index), so this never returns null for a persisted user.
     */
    public function findPrimaryForUser(User $user): UserEmail
    {
        $result = $this->createQueryBuilder('ue')
            ->where('ue.user = :owner')
            ->andWhere('ue.isPrimary = true')
            ->setParameter('owner', $user->id, 'ulid')
            ->getQuery()
            ->getSingleResult()
        ;

        \assert($result instanceof UserEmail);

        return $result;
    }

    /**
     * The row for this address on this user's account, verified or not, or null when the user has no
     * such row. Case-insensitive via the citext column. Used to short-circuit adding an address the user
     * already holds.
     */
    public function findForUserByEmail(User $user, string $email): ?UserEmail
    {
        $result = $this->createQueryBuilder('ue')
            ->where('ue.user = :owner')
            ->andWhere('ue.email = :email')
            ->setParameter('owner', $user->id, 'ulid')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof UserEmail ? $result : null;
    }

    public function persist(UserEmail $userEmail): void
    {
        $this->getEntityManager()->persist($userEmail);
    }

    public function remove(UserEmail $userEmail): void
    {
        $this->getEntityManager()->remove($userEmail);
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
