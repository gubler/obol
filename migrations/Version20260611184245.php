<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the exchange_rate table (daily EUR-pivot rates; see #126).
 */
final class Version20260611184245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create exchange_rate table for daily EUR-pivot rates';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE exchange_rate (id UUID NOT NULL, currency VARCHAR(255) NOT NULL, rate DOUBLE PRECISION NOT NULL, as_of DATE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_exchange_rate_currency_as_of ON exchange_rate (currency, as_of)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE exchange_rate');
    }
}
