<?php

// ABOUTME: Integration tests for ObligationSnapshotRepository owner-scoped finders against a real database.
// ABOUTME: Covers the empty-series null case, latest-wins ordering by the monotonic ULID id, and owner isolation.

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\ObligationSnapshot;
use App\Factory\UserFactory;
use App\Repository\ObligationSnapshotRepository;
use App\ValueObject\CalendarDate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ObligationSnapshotRepositoryTest extends WebTestCase
{
    public function testReturnsNullWhenTheOwnerHasNoSnapshot(): void
    {
        self::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        /** @var ObligationSnapshotRepository $repository */
        $repository = $entityManager->getRepository(ObligationSnapshot::class);

        self::assertNull($repository->findLatestForOwner(UserFactory::createOne()->id));
    }

    public function testReturnsTheOwnerMostRecentlyRecordedSnapshot(): void
    {
        self::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        /** @var ObligationSnapshotRepository $repository */
        $repository = $entityManager->getRepository(ObligationSnapshot::class);

        $owner = UserFactory::createOne();

        // Constructed in order, so the third carries the highest (newest) ULID id.
        $entityManager->persist(new ObligationSnapshot($owner, ['USD' => 4000], CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('today'), new \DateTimeZone('UTC'))));
        $entityManager->persist(new ObligationSnapshot($owner, ['USD' => 4500], CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('today'), new \DateTimeZone('UTC'))));
        $entityManager->persist(new ObligationSnapshot($owner, ['USD' => 4500, 'EUR' => 3000], CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('today'), new \DateTimeZone('UTC'))));
        $entityManager->flush();

        self::assertEqualsCanonicalizing(['USD' => 4500, 'EUR' => 3000], $repository->findLatestForOwner($owner->id)?->obligationsByCurrency);
    }

    public function testEachOwnerSeesOnlyTheirOwnSnapshots(): void
    {
        self::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        /** @var ObligationSnapshotRepository $repository */
        $repository = $entityManager->getRepository(ObligationSnapshot::class);

        $alice = UserFactory::createOne();
        $bob = UserFactory::createOne();

        $entityManager->persist(new ObligationSnapshot($alice, ['USD' => 4000], CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('today'), new \DateTimeZone('UTC'))));
        $entityManager->persist(new ObligationSnapshot($bob, ['EUR' => 9000], CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('today'), new \DateTimeZone('UTC'))));
        $entityManager->flush();

        self::assertEqualsCanonicalizing(['USD' => 4000], $repository->findLatestForOwner($alice->id)?->obligationsByCurrency);
        self::assertEqualsCanonicalizing(['EUR' => 9000], $repository->findLatestForOwner($bob->id)?->obligationsByCurrency);

        self::assertCount(1, $repository->findAllOrderedByRecordedAtForOwner($alice->id));
        self::assertCount(1, $repository->findAllOrderedByRecordedAtForOwner($bob->id));
    }
}
