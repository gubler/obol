<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make user.locale nullable (null = not yet resolved from the browser) and add user.date_format, an
 * independent date-rendering preference. Existing rows are set to NULL so their locale is inferred from
 * the browser on their next request; date_format defaults to locale_default, preserving today's output.
 */
final class Version20260706224522 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make user.locale nullable (re-infer existing rows) and add user.date_format (default locale_default).';
    }

    public function up(Schema $schema): void
    {
        // locale: nullable now (null = unresolved). Blank out existing rows so the browser guess sets a
        // real, region-aware tag on their next request rather than freezing the old app default.
        $this->addSql('ALTER TABLE "user" ALTER locale DROP NOT NULL');
        $this->addSql('UPDATE "user" SET locale = NULL');

        // date_format: independent of locale; existing rows keep today's behavior via locale_default.
        // Add the column with a default to backfill existing rows, then drop it - the entity owns the
        // default (DateFormat::LocaleDefault), so the DB carries none (keeps schema:validate in sync).
        $this->addSql("ALTER TABLE \"user\" ADD date_format VARCHAR(255) DEFAULT 'locale_default' NOT NULL");
        $this->addSql('ALTER TABLE "user" ALTER date_format DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE \"user\" SET locale = 'en-US' WHERE locale IS NULL");
        $this->addSql('ALTER TABLE "user" ALTER locale SET NOT NULL');
        $this->addSql('ALTER TABLE "user" DROP date_format');
    }
}
