<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add first-run onboarding state to the user: a display name and an onboarding-completed stamp.
 * display_name is NOT NULL (an account is never nameless), so add it nullable, backfill existing rows
 * to their email, then flip to NOT NULL. onboarding_completed_at stays nullable (null = not onboarded);
 * existing users are already configured, so stamp them now to route them straight past onboarding.
 */
final class Version20260706165419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.display_name (backfilled to email) and user.onboarding_completed_at (existing users stamped complete).';
    }

    public function up(Schema $schema): void
    {
        // display_name: add nullable so existing rows survive the ADD, backfill to the email (matches
        // the User constructor default), then enforce NOT NULL.
        $this->addSql('ALTER TABLE "user" ADD display_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE "user" SET display_name = email WHERE display_name IS NULL');
        $this->addSql('ALTER TABLE "user" ALTER display_name SET NOT NULL');

        // onboarding_completed_at: nullable (null = not onboarded). Existing accounts already have their
        // currency/timezone from the per-user-settings slice, so mark them complete to skip onboarding.
        $this->addSql('ALTER TABLE "user" ADD onboarding_completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('UPDATE "user" SET onboarding_completed_at = NOW() WHERE onboarding_completed_at IS NULL');
    }

    public function down(Schema $schema): void
    {
        // Reversible: onboarding state, not ownership. Dropping it loses the display name and the
        // completed stamp but leaves the accounts intact.
        $this->addSql('ALTER TABLE "user" DROP display_name');
        $this->addSql('ALTER TABLE "user" DROP onboarding_completed_at');
    }
}
