<?php

// ABOUTME: Doctrine repository for UserEmail rows; resolves a verified address to its row for the firewall.

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserEmail;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
}
