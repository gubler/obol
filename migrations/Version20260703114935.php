<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the user + user_email tables for passwordless magic-link auth: citext (case-insensitive)
 * email columns, plus the two partial unique indexes Doctrine attributes cannot express - exactly
 * one primary address per user, and one owner per verified address.
 */
final class Version20260703114935 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user and user_email tables for passwordless auth (citext + partial unique indexes).';
    }

    public function up(Schema $schema): void
    {
        // citext backs the email columns; must exist before the tables that use it.
        $this->addSql('CREATE EXTENSION IF NOT EXISTS citext');

        $this->addSql('CREATE TABLE "user" (id UUID NOT NULL, email CITEXT NOT NULL, roles JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON "user" (email)');

        $this->addSql('CREATE TABLE user_email (id UUID NOT NULL, email CITEXT NOT NULL, is_primary BOOLEAN NOT NULL, verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_550872CA76ED395 ON user_email (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email_per_user ON user_email (user_id, email)');
        $this->addSql('ALTER TABLE user_email ADD CONSTRAINT FK_550872CA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE');

        // Exactly one primary address per user.
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email_primary ON user_email (user_id) WHERE is_primary');
        // A verified address belongs to exactly one user (unverified rows do not compete, so an address
        // cannot be squatted by leaving it unverified elsewhere).
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email_verified ON user_email (email) WHERE verified_at IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_email DROP CONSTRAINT FK_550872CA76ED395');
        $this->addSql('DROP TABLE user_email');
        $this->addSql('DROP TABLE "user"');
        // The citext extension is left installed: it is cheap, harmless, and dropping it would break
        // if a later migration comes to depend on it.
    }
}
