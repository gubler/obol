<?php

// ABOUTME: Runner for CheckDatabaseIsReachableQuery - one trivial round trip over the app's connection.
// ABOUTME: Answers false rather than throwing when the database is gone; the reason goes to the log.

declare(strict_types=1);

namespace App\Message\Query\System;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: CheckDatabaseIsReachableQuery::class)]
final readonly class CheckDatabaseIsReachableRunner
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $appLogger,
    ) {
    }

    /**
     * Deliberately the cheapest statement that proves a connection end to end - it opens the
     * connection if the pool has none, crosses the network, and reads a result back. It reads no
     * application table on purpose: the question is whether the database answers, and a query
     * against a real table would also fail for reasons that are not about reachability.
     *
     * An unreachable database is an answer to this query, not an exceptional condition, so the
     * failure is caught and reported as false. The exception itself still reaches the log, because
     * the status code alone cannot tell an operator which of the many ways to lose a database
     * happened.
     */
    public function __invoke(CheckDatabaseIsReachableQuery $query): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return true;
        } catch (\Throwable $throwable) {
            $this->appLogger->error('Health check could not reach the database.', [
                'exception' => $throwable,
            ]);

            return false;
        }
    }
}
