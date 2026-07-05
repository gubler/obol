<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Entity\PaymentSource;
use App\Entity\Subscription;
use App\Enum\PaymentGeneration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /**
     * A single subscription owned by the given user, or null when it does not exist or belongs to
     * someone else. The null-cross-owner result is what turns a foreign id into a 404 (see ADR-0015).
     */
    public function findForOwner(Ulid $id, Ulid $ownerId): ?Subscription
    {
        $result = $this->createQueryBuilder('s')
            ->andWhere('s.id = :id')
            ->andWhere('s.owner = :owner')
            ->setParameter('id', $id, UlidType::NAME)
            ->setParameter('owner', $ownerId, UlidType::NAME)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return $result instanceof Subscription ? $result : null;
    }

    /**
     * A user's subscriptions for the homepage listing, ordered by category name then subscription name.
     * Archived subscriptions are excluded unless $includeArchived is true.
     *
     * @return list<Subscription>
     */
    public function findForHomepageForOwner(Ulid $ownerId, bool $includeArchived): array
    {
        // Left join so uncategorized subscriptions (no category) are included; the runner orders the
        // uncategorized group last regardless of this base ordering.
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.category', 'c')
            ->andWhere('s.owner = :owner')
            ->setParameter('owner', $ownerId, UlidType::NAME)
            ->orderBy('c.name', 'ASC')
            ->addOrderBy('s.name', 'ASC')
        ;

        if (!$includeArchived) {
            $qb->andWhere('s.archived = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * A user's active (non-archived) subscriptions - the reporting base.
     *
     * @return list<Subscription>
     */
    public function findActiveForOwner(Ulid $ownerId): array
    {
        return $this->activeForOwnerQb($ownerId)->getQuery()->getResult();
    }

    /**
     * A user's active subscriptions in the given category, or the uncategorized ones when $category is
     * null (the category drill-down report).
     *
     * @return list<Subscription>
     */
    public function findActiveForOwnerByCategory(Ulid $ownerId, ?Category $category): array
    {
        $qb = $this->activeForOwnerQb($ownerId);

        if ($category instanceof Category) {
            // Bind the id with the ULID type: comparing the association to the entity leaves Doctrine to
            // stringify the Ulid as base32, which the uuid column rejects.
            $qb->andWhere('s.category = :category')->setParameter('category', $category->id, UlidType::NAME);
        } else {
            $qb->andWhere('s.category IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * A user's active subscriptions on the given payment source, or the unassigned ones when $source is
     * null (the payment-source drill-down report).
     *
     * @return list<Subscription>
     */
    public function findActiveForOwnerByPaymentSource(Ulid $ownerId, ?PaymentSource $source): array
    {
        $qb = $this->activeForOwnerQb($ownerId);

        if ($source instanceof PaymentSource) {
            // Bind the id with the ULID type (see findActiveForOwnerByCategory).
            $qb->andWhere('s.paymentSource = :source')->setParameter('source', $source->id, UlidType::NAME);
        } else {
            $qb->andWhere('s.paymentSource IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Every subscription due for automatic payment generation, resolved in each owner's local timezone.
     * Due means: not archived, on automatic generation, and its `nextRenewal` reached in the owner's zone.
     *
     * `nextRenewal` is a timezone-naive local date (ADR-0016), so the comparison joins the owner and
     * evaluates `now` in that owner's zone (`AT TIME ZONE`) rather than UTC - a user behind UTC is not
     * charged a day early. `$now` is the application clock, bound as an instant, so generation keys off
     * application time regardless of the database clock. Filtering here (not in PHP) also means the
     * generation sweep loads only the rows it will act on.
     *
     * @return list<Subscription>
     */
    public function findAllPendingPaymentGeneration(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.owner', 'u')
            ->andWhere('s.archived = false')
            ->andWhere('s.paymentGeneration = :automated')
            ->andWhere('s.nextRenewal <= AT_TIME_ZONE(:now, u.timezone)')
            ->setParameter('automated', PaymentGeneration::Automated->value)
            ->setParameter('now', $now->setTimezone(new \DateTimeZone('UTC')), Types::DATETIMETZ_IMMUTABLE)
            ->getQuery()
            ->getResult()
        ;
    }

    private function activeForOwnerQb(Ulid $ownerId): QueryBuilder
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.owner = :owner')
            ->andWhere('s.archived = false')
            ->setParameter('owner', $ownerId, UlidType::NAME)
        ;
    }

    //    /**
    //     * @return Subscription[] Returns an array of Subscription objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Subscription
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
