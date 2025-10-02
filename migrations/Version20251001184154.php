<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251001184154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (id BLOB NOT NULL --(DC2Type:ulid)
        , name VARCHAR(255) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE payment (id BLOB NOT NULL --(DC2Type:ulid)
        , subscription_id BLOB NOT NULL --(DC2Type:ulid)
        , type VARCHAR(255) NOT NULL, amount INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , PRIMARY KEY(id), CONSTRAINT FK_6D28840D9A1887DC FOREIGN KEY (subscription_id) REFERENCES subscription (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6D28840D9A1887DC ON payment (subscription_id)');
        $this->addSql('CREATE TABLE subscription (id BLOB NOT NULL --(DC2Type:ulid)
        , category_id BLOB NOT NULL --(DC2Type:ulid)
        , archived BOOLEAN NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , name VARCHAR(255) NOT NULL, last_paid_date DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , payment_period VARCHAR(255) NOT NULL, payment_period_count INTEGER NOT NULL, cost INTEGER NOT NULL, description CLOB NOT NULL, link CLOB NOT NULL, logo VARCHAR(255) NOT NULL, PRIMARY KEY(id), CONSTRAINT FK_A3C664D312469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_A3C664D312469DE2 ON subscription (category_id)');
        $this->addSql('CREATE TABLE subscription_event (id BLOB NOT NULL --(DC2Type:ulid)
        , subscription_id BLOB NOT NULL --(DC2Type:ulid)
        , type VARCHAR(255) NOT NULL, context CLOB NOT NULL --(DC2Type:json)
        , created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , PRIMARY KEY(id), CONSTRAINT FK_C1960BD49A1887DC FOREIGN KEY (subscription_id) REFERENCES subscription (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C1960BD49A1887DC ON subscription_event (subscription_id)');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , available_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , delivered_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE payment');
        $this->addSql('DROP TABLE subscription');
        $this->addSql('DROP TABLE subscription_event');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
