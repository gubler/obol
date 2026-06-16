<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the payment.advanced_renewal flag: whether recording a payment advanced its subscription's
 * renewal anchor, so removing it rolls the anchor back. Existing rows backfill to false (the safe
 * default: a payment of unknown provenance does not roll the anchor back on deletion).
 */
final class Version20260616192841 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add payment.advanced_renewal flag';
    }

    public function up(Schema $schema): void
    {
        // Add with a default so existing rows backfill to false, then drop the default so the schema
        // matches the ORM metadata (the column carries no database-level default).
        $this->addSql('ALTER TABLE payment ADD advanced_renewal BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE payment ALTER COLUMN advanced_renewal DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment DROP advanced_renewal');
    }
}
