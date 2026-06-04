<?php

// ABOUTME: Guards that the test environment runs against PostgreSQL, not SQLite,
// ABOUTME: so dev, test, and CI cannot silently drift from production's engine.

declare(strict_types=1);

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;

test('the test environment runs against PostgreSQL', function (): void {
    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);

    expect($entityManager->getConnection()->getDatabasePlatform())
        ->toBeInstanceOf(PostgreSQLPlatform::class)
    ;
});
