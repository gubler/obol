<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Narrow the renewal anchor and paid date to calendar dates (DATE), and store the canonical
 * renewal day-of-month so a short month can no longer drift the anchor. See ADR-0021 / ADR-0008.
 *
 * next_renewal and paid_date were TIMESTAMP(0); their time-of-day was never meaningful (a renewal
 * is a naive local date, ADR-0016) and reading it against a zoned instant was the source of the
 * off-by-a-day bug. The conversion truncates to the calendar day.
 *
 * renewal_day is backfilled from the day of the *pre-migration* anchor. An anchor that had already
 * drifted off its intended day (a historical month-overflow) is therefore frozen at the day it
 * currently shows, not retroactively corrected - repairing drifted anchors is a separate concern.
 */
final class Version20260720153222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'next_renewal and paid_date to DATE; add subscription.renewal_day';
    }

    public function up(Schema $schema): void
    {
        // subscription: next_renewal (timestamp -> DATE) + renewal_day (derived from the old anchor).
        $this->addSql('ALTER TABLE subscription ADD next_renewal_date DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE subscription ADD renewal_day INT DEFAULT NULL');
        $this->addSql('UPDATE subscription SET next_renewal_date = next_renewal::date, renewal_day = EXTRACT(DAY FROM next_renewal)');
        $this->addSql('ALTER TABLE subscription ALTER next_renewal_date SET NOT NULL');
        $this->addSql('ALTER TABLE subscription ALTER renewal_day SET NOT NULL');
        $this->addSql('ALTER TABLE subscription DROP next_renewal');
        $this->addSql('ALTER TABLE subscription RENAME COLUMN next_renewal_date TO next_renewal');

        // payment: paid_date (timestamp -> DATE).
        $this->addSql('ALTER TABLE payment ADD paid_date_date DATE DEFAULT NULL');
        $this->addSql('UPDATE payment SET paid_date_date = paid_date::date');
        $this->addSql('ALTER TABLE payment ALTER paid_date_date SET NOT NULL');
        $this->addSql('ALTER TABLE payment DROP paid_date');
        $this->addSql('ALTER TABLE payment RENAME COLUMN paid_date_date TO paid_date');
    }

    public function down(Schema $schema): void
    {
        // Lossy and one-way: the calendar dates come back as timestamps at midnight, and the canonical
        // renewal_day is discarded (the anchor keeps whatever day it currently carries).
        $this->addSql('ALTER TABLE payment ADD paid_date_ts TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE payment SET paid_date_ts = paid_date::timestamp');
        $this->addSql('ALTER TABLE payment ALTER paid_date_ts SET NOT NULL');
        $this->addSql('ALTER TABLE payment DROP paid_date');
        $this->addSql('ALTER TABLE payment RENAME COLUMN paid_date_ts TO paid_date');

        $this->addSql('ALTER TABLE subscription ADD next_renewal_ts TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE subscription SET next_renewal_ts = next_renewal::timestamp');
        $this->addSql('ALTER TABLE subscription ALTER next_renewal_ts SET NOT NULL');
        $this->addSql('ALTER TABLE subscription DROP next_renewal');
        $this->addSql('ALTER TABLE subscription DROP renewal_day');
        $this->addSql('ALTER TABLE subscription RENAME COLUMN next_renewal_ts TO next_renewal');
    }
}
