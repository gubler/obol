<?php

// ABOUTME: Integration tests for ObligationSnapshotRepository::findLatest against a real database.
// ABOUTME: Covers the empty-series null case and latest-wins ordering by the monotonic ULID id.

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\ObligationSnapshot;
use App\Repository\ObligationSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ObligationSnapshotRepositoryTest extends WebTestCase
{
    public function testReturnsNullWhenNoSnapshotHasBeenRecorded(): void
    {
        self::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        /** @var ObligationSnapshotRepository $repository */
        $repository = $entityManager->getRepository(ObligationSnapshot::class);

        self::assertNull($repository->findLatest());
    }

    public function testReturnsTheMostRecentlyRecordedSnapshot(): void
    {
        self::createClient();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        /** @var ObligationSnapshotRepository $repository */
        $repository = $entityManager->getRepository(ObligationSnapshot::class);

        // Constructed in order, so the third carries the highest (newest) ULID id.
        $entityManager->persist(new ObligationSnapshot(['USD' => 4000]));
        $entityManager->persist(new ObligationSnapshot(['USD' => 4500]));
        $entityManager->persist(new ObligationSnapshot(['USD' => 4500, 'EUR' => 3000]));
        $entityManager->flush();

        self::assertEqualsCanonicalizing(['USD' => 4500, 'EUR' => 3000], $repository->findLatest()?->obligationsByCurrency);
    }
}
