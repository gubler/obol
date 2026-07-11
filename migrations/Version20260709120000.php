<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add user.savings_display, the per-user savings-target view preference (month-of / month-before /
 * hidden; see ADR-0009). New accounts default to 'month_of', but existing rows have been experiencing
 * the old hard-coded month-ahead lead, so backfill them to 'month_before' to preserve today's figures.
 * Add NOT NULL with that backfill default in one statement, then drop the DB default: the application
 * owns the default for new rows ('month_of'), not the column.
 */
final class Version20260709120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.savings_display (existing rows backfilled to month_before to preserve current behavior).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"user\" ADD savings_display VARCHAR(255) DEFAULT 'month_before' NOT NULL");
        $this->addSql('ALTER TABLE "user" ALTER savings_display DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP savings_display');
    }
}
