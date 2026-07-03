<?php

// ABOUTME: Integration tests for UserEmailRepository::findVerifiedByEmail against a real database.
// ABOUTME: Also pins the partial unique index that enforces exactly one primary address per user.

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\User;
use App\Entity\UserEmail;
use App\Repository\UserEmailRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserEmailRepositoryTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    private UserEmailRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(UserEmailRepository::class);
    }

    public function testFindsAVerifiedAddressCaseInsensitively(): void
    {
        // The User constructor creates the verified primary UserEmail matching this address.
        $user = new User(email: 'magos@dev88.test');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $found = $this->repository->findVerifiedByEmail('MAGOS@DEV88.TEST');

        self::assertNotNull($found);
        self::assertTrue($found->user->id->equals($user->id));
    }

    public function testDoesNotFindAnUnverifiedAddress(): void
    {
        $user = new User(email: 'magos@dev88.test');
        new UserEmail(user: $user, email: 'unverified@dev88.test', isPrimary: false, verifiedAt: null);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        self::assertNull($this->repository->findVerifiedByEmail('unverified@dev88.test'));
    }

    public function testDoesNotFindAnUnknownAddress(): void
    {
        self::assertNull($this->repository->findVerifiedByEmail('nobody@dev88.test'));
    }

    public function testRejectsASecondPrimaryAddressForTheSameUser(): void
    {
        // The user already has its constructor-created primary; adding another primary must fail.
        $user = new User(email: 'magos@dev88.test');
        new UserEmail(user: $user, email: 'second@dev88.test', isPrimary: true, verifiedAt: new \DateTimeImmutable());
        $this->entityManager->persist($user);

        // The partial unique index (user_id) WHERE is_primary forbids two primary rows per user.
        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }
}
