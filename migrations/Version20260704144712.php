<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Give every user their own presentation settings: display currency, locale, and timezone. These
 * replace the app-global app.display_currency parameter. Add the columns nullable so existing rows
 * survive the ADD, backfill them to the app defaults (the founder gets USD/en-US/America/New_York), then flip to
 * NOT NULL. First-run onboarding (a later slice) captures these for new users.
 */
final class Version20260704144712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-user display currency, locale, and timezone; backfill existing users to the app defaults.';
    }

    public function up(Schema $schema): void
    {
        // 1. Add the columns nullable so existing rows survive the ADD; they are backfilled below.
        $this->addSql('ALTER TABLE "user" ADD display_currency VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD locale VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD timezone VARCHAR(255) DEFAULT NULL');

        // 2. Backfill existing users to the app defaults (matches the User constructor defaults).
        $this->addSql("UPDATE \"user\" SET display_currency = 'USD' WHERE display_currency IS NULL");
        $this->addSql("UPDATE \"user\" SET locale = 'en-US' WHERE locale IS NULL");
        $this->addSql("UPDATE \"user\" SET timezone = 'America/New_York' WHERE timezone IS NULL");

        // 3. Every user now has settings: enforce it.
        $this->addSql('ALTER TABLE "user" ALTER display_currency SET NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER locale SET NOT NULL');
        $this->addSql('ALTER TABLE "user" ALTER timezone SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Reversible: these are presentation preferences, not ownership. Dropping them loses per-user
        // settings but leaves the accounts intact.
        $this->addSql('ALTER TABLE "user" DROP display_currency');
        $this->addSql('ALTER TABLE "user" DROP locale');
        $this->addSql('ALTER TABLE "user" DROP timezone');
    }
}
