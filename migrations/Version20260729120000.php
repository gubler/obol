<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the two framework-owned tables that make an application container disposable: `sessions`
 * for Symfony's PdoSessionHandler, and `cache_items` for the DoctrineDbalAdapter behind the
 * application cache pool. Both hold state that has to outlive a container - a signed-in session, the
 * scheduler's missed-run checkpoint, the magic-link replay guard - and the production image declares
 * no volume for var/, so the filesystem is no longer somewhere any of it can live. See
 * reference/adr/0026.
 *
 * Owned here rather than by the adapters' own auto-create, for the same reason messenger_messages is:
 * the runtime database role should not need DDL rights. On PostgreSQL that fallback could not help
 * anyway - the statement that hit the missing table has already aborted the surrounding transaction,
 * so the CREATE TABLE attempted from the exception handler fails too.
 *
 * The column shapes mirror PdoSessionHandler::buildSchemaTable() and
 * DoctrineDbalAdapter::buildSchemaTable() on their pgsql branches exactly, so that
 * doctrine:migrations:diff stays quiet: doctrine-bundle registers schema listeners that add both
 * tables to the mapping-derived schema, and DBAL compares columns by generated declaration SQL -
 * which is why sess_data (declared binary) and item_data (declared blob) can both be BYTEA without
 * provoking an ALTER.
 */
final class Version20260729120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create sessions and cache_items: database-backed sessions and application cache pool.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sessions (sess_id VARCHAR(128) NOT NULL, sess_data BYTEA NOT NULL, sess_lifetime INT NOT NULL, sess_time INT NOT NULL, PRIMARY KEY (sess_id))');
        $this->addSql('CREATE INDEX sess_lifetime_idx ON sessions (sess_lifetime)');
        $this->addSql('CREATE TABLE cache_items (item_id VARCHAR(255) NOT NULL, item_data BYTEA NOT NULL, item_lifetime INT DEFAULT NULL, item_time INT NOT NULL, PRIMARY KEY (item_id))');
    }

    public function down(Schema $schema): void
    {
        // Reversible, but not free: dropping sessions signs every user out and drops them back onto
        // the remember-me cookie, and dropping cache_items re-arms every outstanding magic link for
        // the remainder of its lifetime.
        $this->addSql('DROP TABLE sessions');
        $this->addSql('DROP TABLE cache_items');
    }
}
