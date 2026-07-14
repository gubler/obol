<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the system_settings singleton - app-global, operator-controlled configuration (see ADR-0020).
 * A structural single row: the smallint id is pinned to 1 by a CHECK (id = 1) constraint, so together
 * with the primary key the table can hold at most one row. The single row is seeded here so reads never
 * have to create it. The first setting is public_signup_enabled, defaulting to false (a fresh deploy is
 * closed to self-registration).
 */
final class Version20260714120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the system_settings singleton (smallint id pinned to 1, seeded row, public_signup_enabled).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE system_settings (id SMALLINT NOT NULL, public_signup_enabled BOOLEAN NOT NULL, PRIMARY KEY(id), CONSTRAINT system_settings_singleton CHECK (id = 1))');
        $this->addSql('INSERT INTO system_settings (id, public_signup_enabled) VALUES (1, false)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE system_settings');
    }
}
