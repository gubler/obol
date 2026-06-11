<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Split subscription.cost and payment.amount into the Money embeddable's columns
 * (*_amount + *_currency), backfilling every existing row's currency to USD. See #128.
 */
final class Version20260611193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migrate subscription.cost and payment.amount to the Money embeddable (backfill USD)';
    }

    public function up(Schema $schema): void
    {
        // Add the split columns nullable, copy the old integer cost across with a USD currency, then
        // tighten to NOT NULL and drop the original column - existing data is single-currency USD.
        $this->addSql('ALTER TABLE subscription ADD cost_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE subscription ADD cost_currency VARCHAR(255) DEFAULT NULL');
        $this->addSql("UPDATE subscription SET cost_amount = cost, cost_currency = 'USD'");
        $this->addSql('ALTER TABLE subscription ALTER cost_amount SET NOT NULL');
        $this->addSql('ALTER TABLE subscription ALTER cost_currency SET NOT NULL');
        $this->addSql('ALTER TABLE subscription DROP cost');

        $this->addSql('ALTER TABLE payment ADD amount_amount INT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD amount_currency VARCHAR(255) DEFAULT NULL');
        $this->addSql("UPDATE payment SET amount_amount = amount, amount_currency = 'USD'");
        $this->addSql('ALTER TABLE payment ALTER amount_amount SET NOT NULL');
        $this->addSql('ALTER TABLE payment ALTER amount_currency SET NOT NULL');
        $this->addSql('ALTER TABLE payment DROP amount');
    }

    public function down(Schema $schema): void
    {
        // Collapse back to a single integer column, discarding the currency (the prior schema was
        // implicitly USD).
        $this->addSql('ALTER TABLE subscription ADD cost INT DEFAULT NULL');
        $this->addSql('UPDATE subscription SET cost = cost_amount');
        $this->addSql('ALTER TABLE subscription ALTER cost SET NOT NULL');
        $this->addSql('ALTER TABLE subscription DROP cost_amount');
        $this->addSql('ALTER TABLE subscription DROP cost_currency');

        $this->addSql('ALTER TABLE payment ADD amount INT DEFAULT NULL');
        $this->addSql('UPDATE payment SET amount = amount_amount');
        $this->addSql('ALTER TABLE payment ALTER amount SET NOT NULL');
        $this->addSql('ALTER TABLE payment DROP amount_amount');
        $this->addSql('ALTER TABLE payment DROP amount_currency');
    }
}
