<?php

// ABOUTME: Prunes the database-backed cache pools on the daily schedule, so cache_items cannot grow forever.
// ABOUTME: Nothing else removes those rows: Symfony never calls prune() on its own, and the adapter's
// ABOUTME: opportunistic cleanup only reaches rows a read happens to touch.

declare(strict_types=1);

namespace App\Message\Scheduler;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\PruneableInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Unlike its sibling scheduler adapters, this one does the work rather than dispatching a command.
 * Those exist because their work has a second caller - a console command shares the same Command -
 * and pruning already has its manual trigger in the framework's own cache:pool:prune.
 */
#[AsMessageHandler(bus: 'command.bus', handles: PruneExpiredCacheItemsMessage::class)]
final readonly class PruneExpiredCacheItemsHandler
{
    /**
     * The database-backed pools, named rather than collected from the cache.pool tag: the system pool
     * carries that tag too, and it lives on the container filesystem where it is rebuilt from the
     * image anyway (ADR-0026), so pruning it would be file churn for nothing. The replay guard is a
     * namespaced pool of its own, and its rows are the ones that grow without bound, so it has to be
     * named alongside the application pool rather than assumed to be covered by it.
     */
    public function __construct(
        #[Autowire(service: 'cache.app')]
        private PruneableInterface $applicationPool,
        #[Autowire(service: 'cache.login_link')]
        private PruneableInterface $loginLinkPool,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(PruneExpiredCacheItemsMessage $message): void
    {
        foreach (['application' => $this->applicationPool, 'login_link' => $this->loginLinkPool] as $name => $pool) {
            if (!$pool->prune()) {
                $this->logger->error('Could not prune a cache pool.', ['pool' => $name]);
            }
        }
    }
}
