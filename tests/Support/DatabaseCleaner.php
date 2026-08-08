<?php

// ABOUTME: Empties every application table for the browser tests, which commit their rows instead of
// ABOUTME: rolling them back, in an order the foreign keys allow.

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\DBAL\Connection;

/**
 * DAMA rolls back the transaction around each test, so nothing normally survives one. The Panther
 * tests opt out of that (#[SkipDatabaseRollback]) because their writes have to be visible to a second
 * process, which means they commit - and have to clean up after themselves or leak into the next run.
 *
 * DELETE rather than TRUNCATE. TRUNCATE needs a privilege of its own, and the application never uses
 * it, so granting it to the runtime role would widen what a SQL injection can reach in production to
 * suit a test-only convenience (see reference/adr/0030). The cost is that the order matters, which is
 * the reason this list lives in one place rather than being restated per test.
 */
final class DatabaseCleaner
{
    /**
     * Children before parents. Every table here descends from `user`, directly or through
     * `subscription`, so emptying them in this order never crosses a foreign key.
     */
    private const array TABLES_CHILDREN_FIRST = [
        'subscription_event',
        'payment',
        'subscription',
        'category',
        'payment_source',
        'obligation_snapshot',
        'passkey_credential',
        'user_email',
        '"user"',
    ];

    public static function clear(Connection $connection): void
    {
        foreach (self::TABLES_CHILDREN_FIRST as $table) {
            $connection->executeStatement('DELETE FROM ' . $table);
        }
    }
}
