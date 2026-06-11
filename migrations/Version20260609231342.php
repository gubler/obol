<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds the subscription.paused flag (payment-generation suppression; see ADR-0008).
 */
final class Version20260609231342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subscription.payment_generation (automated|manual); see ADR-0008';
    }

    public function up(Schema $schema): void
    {
        // Add defaulting to automated so existing rows backfill, then drop the default to match the
        // ORM mapping (the entity always supplies a value, so no DB-level default is carried).
        $this->addSql("ALTER TABLE subscription ADD payment_generation VARCHAR(255) DEFAULT 'automated' NOT NULL");
        $this->addSql('ALTER TABLE subscription ALTER payment_generation DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP payment_generation');
    }
}
