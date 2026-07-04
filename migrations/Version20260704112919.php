<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

/**
 * Give every subscription and payment an immutable owner (a User), turning the single-tenant
 * dogfooding data into the founder's. Ordered and irreversible: add the FK columns nullable, seed the
 * founder account (plus a primary verified email), backfill all existing rows to the founder, then
 * flip the columns to NOT NULL. See ADR-0015.
 *
 * Deploy gate: the prod mailer must be live and `app:mailer:smoke` must pass BEFORE this runs, or the
 * founder's first magic-link login has no working mailbox to reach and locks them out.
 */
final class Version20260704112919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add immutable owner to subscription + payment; seed the founder and backfill existing rows (irreversible).';
    }

    public function up(Schema $schema): void
    {
        // 1. Add the owner columns nullable so existing rows survive the ADD; they are backfilled below.
        $this->addSql('ALTER TABLE subscription ADD owner_user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE payment ADD owner_user_id UUID DEFAULT NULL');

        // 2. Seed the founder: the current dogfooding data is Magos's. ROLE_USER is added at runtime by
        //    User::getRoles(), so only ROLE_ADMIN is stored. The primary email is verified because the
        //    operator seeding it vouches for control of the address (mirrors app:user:create).
        $founderId = (new Ulid())->toRfc4122();
        $emailId = (new Ulid())->toRfc4122();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $founderEmail = 'daryl@dev88.co';

        $this->addSql(
            'INSERT INTO "user" (id, email, roles, created_at) VALUES (?, ?, ?, ?)',
            [$founderId, $founderEmail, json_encode(['ROLE_ADMIN']), $now],
        );
        $this->addSql(
            'INSERT INTO user_email (id, user_id, email, is_primary, verified_at, created_at) VALUES (?, ?, ?, true, ?, ?)',
            [$emailId, $founderId, $founderEmail, $now, $now],
        );

        // 3. Backfill every existing subscription and payment to the founder.
        $this->addSql('UPDATE subscription SET owner_user_id = ? WHERE owner_user_id IS NULL', [$founderId]);
        $this->addSql('UPDATE payment SET owner_user_id = ? WHERE owner_user_id IS NULL', [$founderId]);

        // 4. Every row now has an owner: enforce it, and add the foreign keys and lookup indexes.
        $this->addSql('ALTER TABLE subscription ALTER owner_user_id SET NOT NULL');
        $this->addSql('ALTER TABLE payment ALTER owner_user_id SET NOT NULL');

        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D32B18554A FOREIGN KEY (owner_user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_A3C664D32B18554A ON subscription (owner_user_id)');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D2B18554A FOREIGN KEY (owner_user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_6D28840D2B18554A ON payment (owner_user_id)');
    }

    public function down(Schema $schema): void
    {
        // Irreversible: dropping the columns would discard ownership, and there is no safe way to guess
        // which user each row belonged to once the founder backfill has run.
        $this->throwIrreversibleMigrationException(
            'The owner backfill cannot be reversed: ownership information would be lost.',
        );
    }
}
