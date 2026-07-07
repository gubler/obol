<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remap user.date_format to the simplified Long/Medium/Short/ISO styles. The old locale_default
 * rendered the locale's medium form, so it becomes 'medium' (the new default); the ISO pattern becomes
 * 'iso'; the hand-encoded numeric slash formats become 'short' (the locale's own numeric short form).
 */
final class Version20260707120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remap user.date_format from locale_default/pattern values to Long/Medium/Short/ISO styles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE \"user\" SET date_format = 'medium' WHERE date_format = 'locale_default'");
        $this->addSql("UPDATE \"user\" SET date_format = 'iso' WHERE date_format = 'yyyy-MM-dd'");
        $this->addSql("UPDATE \"user\" SET date_format = 'short' WHERE date_format IN ('MM/dd/yyyy', 'dd/MM/yyyy')");
    }

    public function down(Schema $schema): void
    {
        // Irreversible: 'medium' collapses both the old locale_default and any Long style, and 'short'
        // collapses both slash formats, so the original value cannot be recovered.
        throw new \RuntimeException('Version20260707120000 is irreversible (date_format styles were collapsed).');
    }
}
