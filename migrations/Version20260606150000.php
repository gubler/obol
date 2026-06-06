<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replace subscription.last_paid_date with a stored next_renewal anchor, and
 * split payment.created_at into paid_date (the charge date) plus a real
 * created_at row timestamp. See ADR-0008.
 */
final class Version20260606150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renewal anchor (next_renewal) and payment paid_date/created_at split';
    }

    public function up(Schema $schema): void
    {
        // subscription: last_paid_date -> next_renewal (advanced by one billing interval)
        $this->addSql('ALTER TABLE subscription ADD next_renewal TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql("UPDATE subscription SET next_renewal = last_paid_date + (payment_period_count * (CASE payment_period WHEN 'week' THEN INTERVAL '1 week' WHEN 'month' THEN INTERVAL '1 month' WHEN 'year' THEN INTERVAL '1 year' END))");
        $this->addSql('ALTER TABLE subscription ALTER next_renewal SET NOT NULL');
        $this->addSql('ALTER TABLE subscription DROP last_paid_date');

        // payment: created_at held the paid date; rename it and add a real created_at
        $this->addSql('ALTER TABLE payment RENAME COLUMN created_at TO paid_date');
        $this->addSql('ALTER TABLE payment ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql('UPDATE payment SET created_at = paid_date');
        $this->addSql('ALTER TABLE payment ALTER created_at SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscription ADD last_paid_date TIMESTAMP(0) WITHOUT TIME ZONE');
        $this->addSql("UPDATE subscription SET last_paid_date = next_renewal - (payment_period_count * (CASE payment_period WHEN 'week' THEN INTERVAL '1 week' WHEN 'month' THEN INTERVAL '1 month' WHEN 'year' THEN INTERVAL '1 year' END))");
        $this->addSql('ALTER TABLE subscription ALTER last_paid_date SET NOT NULL');
        $this->addSql('ALTER TABLE subscription DROP next_renewal');

        $this->addSql('ALTER TABLE payment DROP created_at');
        $this->addSql('ALTER TABLE payment RENAME COLUMN paid_date TO created_at');
    }
}
