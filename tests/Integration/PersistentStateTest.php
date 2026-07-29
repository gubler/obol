<?php

// ABOUTME: Guards that deploy-durable state lives in PostgreSQL rather than on the container
// ABOUTME: filesystem: the sessions table, the cache_items table, and which pool uses which.

declare(strict_types=1);

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\PdoSessionHandler;

final class PersistentStateTest extends WebTestCase
{
    public function testTheSessionsTableExists(): void
    {
        self::assertTrue($this->connection()->createSchemaManager()->tablesExist(['sessions']));
    }

    public function testTheCacheItemsTableExists(): void
    {
        self::assertTrue($this->connection()->createSchemaManager()->tablesExist(['cache_items']));
    }

    /**
     * The test environment swaps in mock_file session storage, which bypasses the handler entirely,
     * so this asserts the wiring rather than the behaviour: the handler the container builds here is
     * the one dev and production actually run through.
     */
    public function testTheSessionHandlerIsDatabaseBacked(): void
    {
        self::assertInstanceOf(PdoSessionHandler::class, self::getContainer()->get(id: 'session.handler'));
    }

    public function testTheApplicationCachePoolWritesToTheCacheItemsTable(): void
    {
        /** @var CacheItemPoolInterface $pool */
        $pool = self::getContainer()->get(id: CacheItemPoolInterface::class);

        self::assertSame(1, $this->rowsWrittenBy($pool, 'application_pool_probe'));
    }

    public function testTheLoginLinkPoolSharesTheDatabaseBackedApplicationPool(): void
    {
        /** @var CacheItemPoolInterface $pool */
        $pool = self::getContainer()->get(id: 'cache.login_link');

        self::assertSame(1, $this->rowsWrittenBy($pool, 'login_link_pool_probe'));
    }

    /**
     * The system pool holds what the image already contains - compiled metadata, the asset map - so
     * rebuilding it per container is correct and it must stay off the database.
     */
    public function testTheSystemCachePoolStaysOffTheDatabase(): void
    {
        /** @var CacheItemPoolInterface $pool */
        $pool = self::getContainer()->get(id: 'cache.system');

        self::assertSame(0, $this->rowsWrittenBy($pool, 'system_pool_probe'));
    }

    /**
     * Counts rows the pool added to cache_items while saving one probe item. Asserting on the
     * backing table rather than the pool's class keeps this honest about what "database-backed"
     * means, and survives the profiler's traceable decorator wrapping every pool in the test
     * environment.
     */
    private function rowsWrittenBy(CacheItemPoolInterface $pool, string $key): int
    {
        $connection = $this->connection();
        $before = $this->cacheItemCount($connection);

        $item = $pool->getItem($key);
        $item->set('probe');

        $pool->save($item);

        return $this->cacheItemCount($connection) - $before;
    }

    private function cacheItemCount(Connection $connection): int
    {
        return (int) $connection->fetchOne('SELECT count(*) FROM cache_items');
    }

    private function connection(): Connection
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);

        return $entityManager->getConnection();
    }
}
