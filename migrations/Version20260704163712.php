<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Give every category and payment source an immutable owner (a User), extending per-user isolation to
 * the two Category-shaped entities. Ordered and irreversible: add the FK columns nullable, backfill all
 * existing rows to the founder (the current dogfooding data is Magos's, seeded by the founder
 * migration), then flip the columns to NOT NULL. See ADR-0015.
 */
final class Version20260704163712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add immutable owner to category + payment_source; backfill existing rows to the founder (irreversible).';
    }

    public function up(Schema $schema): void
    {
        // 1. Add the owner columns nullable so existing rows survive the ADD; they are backfilled below.
        $this->addSql('ALTER TABLE category ADD owner_user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE payment_source ADD owner_user_id UUID DEFAULT NULL');

        // 2. Backfill every existing category and payment source to the founder (seeded by the founder
        //    migration). All pre-isolation data belongs to the single dogfooding account.
        $this->addSql('UPDATE category SET owner_user_id = (SELECT id FROM "user" WHERE email = \'daryl@dev88.co\') WHERE owner_user_id IS NULL');
        $this->addSql('UPDATE payment_source SET owner_user_id = (SELECT id FROM "user" WHERE email = \'daryl@dev88.co\') WHERE owner_user_id IS NULL');

        // 3. Every row now has an owner: enforce it, and add the foreign keys and lookup indexes.
        $this->addSql('ALTER TABLE category ALTER owner_user_id SET NOT NULL');
        $this->addSql('ALTER TABLE payment_source ALTER owner_user_id SET NOT NULL');

        $this->addSql('ALTER TABLE category ADD CONSTRAINT FK_64C19C12B18554A FOREIGN KEY (owner_user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_64C19C12B18554A ON category (owner_user_id)');
        $this->addSql('ALTER TABLE payment_source ADD CONSTRAINT FK_7AB2E4E52B18554A FOREIGN KEY (owner_user_id) REFERENCES "user" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_7AB2E4E52B18554A ON payment_source (owner_user_id)');
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
