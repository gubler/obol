<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Give a category a color and an icon (#212). Add both columns with a default so existing rows
 * backfill - a neutral Blue swatch and the neutral Tag icon - then drop the default so the schema
 * matches the ORM metadata (the columns carry no database-level default; new rows are seeded by the
 * entity, color randomly and icon to Tag).
 */
final class Version20260616211727 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add category.color and category.icon';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE category ADD color VARCHAR(255) NOT NULL DEFAULT 'blue'");
        $this->addSql("ALTER TABLE category ADD icon VARCHAR(255) NOT NULL DEFAULT 'tag'");
        $this->addSql('ALTER TABLE category ALTER COLUMN color DROP DEFAULT');
        $this->addSql('ALTER TABLE category ALTER COLUMN icon DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP color');
        $this->addSql('ALTER TABLE category DROP icon');
    }
}
