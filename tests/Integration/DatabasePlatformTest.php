<?php

// ABOUTME: Guards that the test environment runs against PostgreSQL, not SQLite,
// ABOUTME: so dev, test, and CI cannot silently drift from production's engine.

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DatabasePlatformTest extends WebTestCase
{
    public function testTheTestEnvironmentRunsAgainstPostgreSQL(): void
    {
        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);

        self::assertInstanceOf(
            PostgreSQLPlatform::class,
            $entityManager->getConnection()->getDatabasePlatform(),
        );
    }
}
