<?php

// ABOUTME: Doctrine repository for User accounts.

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
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
}
