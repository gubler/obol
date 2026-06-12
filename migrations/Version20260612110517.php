<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the obligation_snapshot table (weekly native-per-currency monthly obligation; see #142, ADR-0010).
 */
final class Version20260612110517 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create obligation_snapshot table for the weekly per-currency obligation series';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE obligation_snapshot (id UUID NOT NULL, obligations_by_currency JSON NOT NULL, recorded_at DATE NOT NULL, PRIMARY KEY (id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE obligation_snapshot');
    }
}
