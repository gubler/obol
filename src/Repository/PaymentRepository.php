<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * A single payment owned by the given user, or null when it does not exist or belongs to someone
     * else. The owner is denormalized onto the payment, so this needs no join through Subscription.
     */
    public function findForOwner(Ulid $id, Ulid $ownerId): ?Payment
    {
        $result = $this->createQueryBuilder('p')
            ->andWhere('p.id = :id')
            ->andWhere('p.owner = :owner')
            ->setParameter('id', $id, UlidType::NAME)
            ->setParameter('owner', $ownerId, UlidType::NAME)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof Payment ? $result : null;
    }
}
