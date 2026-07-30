<?php

// ABOUTME: Proves the scheduled prune deletes expired cache_items rows and leaves live ones alone.
// ABOUTME: Covers the magic-link replay guard's own pool, the only writer that grows without bound.

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Lib\Bus\CommandBus;
use App\Message\Scheduler\PruneExpiredCacheItemsMessage;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CacheItemPruningTest extends WebTestCase
{
    private const string EXPIRED_KEY = 'prune_probe_stale_app';

    private const string LIVE_KEY = 'prune_probe_fresh_app';

    private const string PERMANENT_KEY = 'prune_probe_endless_app';

    private const string EXPIRED_GUARD_KEY = 'prune_probe_stale_guard';

    private const string LIVE_GUARD_KEY = 'prune_probe_fresh_guard';

    public function testExpiredRowsAreDeletedAndLiveOnesSurvive(): void
    {
        $this->store($this->applicationPool(), self::PERMANENT_KEY, lifetime: null);
        $this->store($this->applicationPool(), self::LIVE_KEY, lifetime: 3600);
        $this->store($this->applicationPool(), self::EXPIRED_KEY, lifetime: 3600);
        $this->store($this->loginLinkPool(), self::LIVE_GUARD_KEY, lifetime: null);
        $this->store($this->loginLinkPool(), self::EXPIRED_GUARD_KEY, lifetime: null);

        $this->backdatePast([self::EXPIRED_KEY, self::EXPIRED_GUARD_KEY]);

        self::getContainer()->get(CommandBus::class)->dispatch(new PruneExpiredCacheItemsMessage());

        self::assertFalse($this->rowExists(self::EXPIRED_KEY), 'an expired application-pool row should be gone');
        self::assertFalse($this->rowExists(self::EXPIRED_GUARD_KEY), 'an expired replay-guard row should be gone');

        // The replay guard is a separate namespaced pool over the same table, so pruning only the
        // application pool would leave exactly the rows that grow without bound.
        self::assertTrue($this->rowExists(self::LIVE_GUARD_KEY), 'a live replay-guard row should survive');
        self::assertTrue($this->rowExists(self::LIVE_KEY), 'a live application-pool row should survive');

        // The scheduler checkpoint and the stop-workers signal are written without an expiry, and a
        // prune that took them would re-fire missed schedules and break worker restarts.
        self::assertTrue($this->rowExists(self::PERMANENT_KEY), 'a row with no expiry should survive');
    }

    private function store(CacheItemPoolInterface $pool, string $key, ?int $lifetime): void
    {
        $item = $pool->getItem($key);
        $item->set('probe');
        $item->expiresAfter($lifetime);

        $pool->save($item);
    }

    /**
     * Moves a row's write time far enough into the past that its lifetime has elapsed. Sleeping for a
     * real expiry is the only alternative, and the adapter drops an already-expired item on save
     * rather than writing the row this needs to exist.
     *
     * @param list<string> $keys
     */
    private function backdatePast(array $keys): void
    {
        foreach ($keys as $key) {
            $this->connection()->executeStatement(
                'UPDATE cache_items SET item_time = item_time - 86400 WHERE item_id LIKE :suffix',
                ['suffix' => '%' . $key],
            );
        }
    }

    private function rowExists(string $key): bool
    {
        return (bool) $this->connection()->fetchOne(
            'SELECT count(*) FROM cache_items WHERE item_id LIKE :suffix',
            ['suffix' => '%' . $key],
        );
    }

    private function applicationPool(): CacheItemPoolInterface
    {
        return self::getContainer()->get(id: CacheItemPoolInterface::class);
    }

    private function loginLinkPool(): CacheItemPoolInterface
    {
        return self::getContainer()->get(id: 'cache.login_link');
    }

    private function connection(): Connection
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);

        return $entityManager->getConnection();
    }
}
