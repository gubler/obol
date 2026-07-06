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

    public function testFindForOwnerIdReturnsTheUsersRowsPrimaryThenVerifiedThenPending(): void
    {
        $user = new User(email: 'primary@dev88.test');
        new UserEmail(user: $user, email: 'pending@dev88.test', isPrimary: false, verifiedAt: null);
        new UserEmail(user: $user, email: 'verified@dev88.test', isPrimary: false, verifiedAt: new \DateTimeImmutable());
        // A second user's address must never leak into the first user's list.
        $other = new User(email: 'other@dev88.test');
        $this->entityManager->persist($user);
        $this->entityManager->persist($other);
        $this->entityManager->flush();

        $rows = $this->repository->findForOwnerId($user->id);

        $addresses = array_map(static fn (UserEmail $row): string => $row->email, $rows);
        self::assertSame(['primary@dev88.test', 'verified@dev88.test', 'pending@dev88.test'], $addresses);
    }

    public function testFindForOwnerScopesToTheOwner(): void
    {
        $user = new User(email: 'owner@dev88.test');
        $secondary = new UserEmail(user: $user, email: 'secondary@dev88.test', isPrimary: false, verifiedAt: null);
        $other = new User(email: 'intruder@dev88.test');
        $this->entityManager->persist($user);
        $this->entityManager->persist($other);
        $this->entityManager->flush();

        self::assertNotNull($this->repository->findForOwner($secondary->id, $user->id));
        self::assertNull($this->repository->findForOwner($secondary->id, $other->id));
    }

    public function testFindPrimaryForUserReturnsThePrimaryRow(): void
    {
        $user = new User(email: 'primary@dev88.test');
        new UserEmail(user: $user, email: 'secondary@dev88.test', isPrimary: false, verifiedAt: new \DateTimeImmutable());
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $primary = $this->repository->findPrimaryForUser($user);

        self::assertSame('primary@dev88.test', $primary->email);
        self::assertTrue($primary->isPrimary);
    }

    public function testFindForUserByEmailMatchesAnyRowCaseInsensitively(): void
    {
        $user = new User(email: 'owner@dev88.test');
        new UserEmail(user: $user, email: 'pending@dev88.test', isPrimary: false, verifiedAt: null);
        $other = new User(email: 'other@dev88.test');
        $this->entityManager->persist($user);
        $this->entityManager->persist($other);
        $this->entityManager->flush();

        self::assertNotNull($this->repository->findForUserByEmail($user, 'PENDING@DEV88.TEST'));
        self::assertNotNull($this->repository->findForUserByEmail($user, 'owner@dev88.test'));
        // The same address on a different user is not "on this user's account".
        self::assertNull($this->repository->findForUserByEmail($other, 'pending@dev88.test'));
    }
}
