<?php

// ABOUTME: Doctrine repository for the PaymentSource entity.
// ABOUTME: Standard ServiceEntityRepository; lookups go through the query runner layer.

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PaymentSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PaymentSource>
 */
class PaymentSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PaymentSource::class);
    }
}
