<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make a subscription's category optional: drop the NOT NULL constraint on subscription.category_id
 * so a subscription can be left uncategorized. Existing rows already carry a category, so nothing to
 * backfill; the reverse re-adds the constraint (which fails if any uncategorized rows exist).
 */
final class Version20260616195612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make subscription.category_id nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription ALTER category_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription ALTER category_id SET NOT NULL');
    }
}
