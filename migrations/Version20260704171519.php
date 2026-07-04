<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Give every obligation snapshot an immutable owner (a User), narrowing the series from the app's single
 * global obligation to one series per user. Ordered and irreversible: add the FK column nullable, backfill
 * all existing rows to the founder (the current dogfooding data is Magos's, seeded by the founder
 * migration), then flip the column to NOT NULL. See ADR-0015.
 */
final class Version20260704171519 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add immutable owner to obligation_snapshot; backfill existing rows to the founder (irreversible).';
    }

    public function up(Schema $schema): void
    {
        // 1. Add the owner column nullable so existing rows survive the ADD; they are backfilled below.
        $this->addSql('ALTER TABLE obligation_snapshot ADD owner_user_id UUID DEFAULT NULL');

        // 2. Backfill every existing snapshot to the founder (seeded by the founder migration). All
        //    pre-isolation snapshots belong to the single dogfooding account.
        $this->addSql('UPDATE obligation_snapshot SET owner_user_id = (SELECT id FROM "user" WHERE email = \'daryl@dev88.co\') WHERE owner_user_id IS NULL');

        // 3. Every row now has an owner: enforce it, and add the foreign key and lookup index.
        $this->addSql('ALTER TABLE obligation_snapshot ALTER owner_user_id SET NOT NULL');
        $this->addSql('ALTER TABLE obligation_snapshot ADD CONSTRAINT FK_FD1EACA32B18554A FOREIGN KEY (owner_user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_FD1EACA32B18554A ON obligation_snapshot (owner_user_id)');
    }

    public function down(Schema $schema): void
    {
        // Irreversible: dropping the column would discard ownership, and there is no safe way to guess
        // which user each snapshot belonged to once the founder backfill has run.
        $this->throwIrreversibleMigrationException(
            'The owner backfill cannot be reversed: ownership information would be lost.',
        );
    }
}
