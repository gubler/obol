<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add the tile color swatch to subscriptions, backfilling existing rows with a random palette color.
 */
final class Version20260607134847 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add color to subscription and backfill existing rows with a random TileColor swatch';
    }

    public function up(Schema $schema): void
    {
        // Add nullable first so existing rows are valid, then backfill, then enforce NOT NULL.
        $this->addSql('ALTER TABLE subscription ADD color VARCHAR(255) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE subscription
            SET color = (ARRAY[
                'red','magenta','pink','orange','brown','gold','lime','green','emerald',
                'teal','cyan','blue','indigo','violet','purple','slate','grey','charcoal'
            ])[floor(random() * 18) + 1]
            WHERE color IS NULL
            SQL);
        $this->addSql('ALTER TABLE subscription ALTER COLUMN color SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP color');
    }
}
