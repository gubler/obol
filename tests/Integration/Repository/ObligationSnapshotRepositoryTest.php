<?php

// ABOUTME: Integration tests for ObligationSnapshotRepository::findLatest against a real database.
// ABOUTME: Covers the empty-series null case and latest-wins ordering by the monotonic ULID id.

declare(strict_types=1);

use App\Entity\ObligationSnapshot;
use App\Repository\ObligationSnapshotRepository;
use Doctrine\ORM\EntityManagerInterface;

test('returns null when no snapshot has been recorded', function (): void {
    $this->createClient();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
    /** @var ObligationSnapshotRepository $repository */
    $repository = $entityManager->getRepository(ObligationSnapshot::class);

    expect($repository->findLatest())->toBeNull();
});

test('returns the most recently recorded snapshot', function (): void {
    $this->createClient();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
    /** @var ObligationSnapshotRepository $repository */
    $repository = $entityManager->getRepository(ObligationSnapshot::class);

    // Constructed in order, so the third carries the highest (newest) ULID id.
    $entityManager->persist(new ObligationSnapshot(['USD' => 4000]));
    $entityManager->persist(new ObligationSnapshot(['USD' => 4500]));
    $entityManager->persist(new ObligationSnapshot(['USD' => 4500, 'EUR' => 3000]));
    $entityManager->flush();

    expect($repository->findLatest()?->obligationsByCurrency)->toEqual(['USD' => 4500, 'EUR' => 3000]);
});
