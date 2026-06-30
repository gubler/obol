<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260630095537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the payment_source table and add the nullable subscription.payment_source_id link.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE payment_source (id UUID NOT NULL, name VARCHAR(255) NOT NULL, comment TEXT NOT NULL, color VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE subscription ADD payment_source_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D3D6153349 FOREIGN KEY (payment_source_id) REFERENCES payment_source (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_A3C664D3D6153349 ON subscription (payment_source_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT FK_A3C664D3D6153349');
        $this->addSql('DROP INDEX IDX_A3C664D3D6153349');
        $this->addSql('ALTER TABLE subscription DROP payment_source_id');
        $this->addSql('DROP TABLE payment_source');
    }
}
