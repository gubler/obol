<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Passkeys (WebAuthn). Add User.user_handle - the stable opaque identifier passkeys bind to - and the
 * passkey_credential table (one row per registered authenticator). user_handle is added nullable so
 * existing rows survive the ADD, backfilled with a random UUID per user, then flipped to NOT NULL +
 * unique. The credential columns use the shape the WebAuthn bundle's DBAL types read/write.
 */
final class Version20260705132357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add User.user_handle (backfilled) and the passkey_credential table for WebAuthn passkeys.';
    }

    public function up(Schema $schema): void
    {
        // 1. Add user_handle nullable so existing rows survive the ADD; backfill each with its own
        //    random UUID, then enforce NOT NULL + unique.
        $this->addSql('ALTER TABLE "user" ADD user_handle UUID DEFAULT NULL');
        $this->addSql('UPDATE "user" SET user_handle = gen_random_uuid() WHERE user_handle IS NULL');
        $this->addSql('ALTER TABLE "user" ALTER user_handle SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649F4D23BE4 ON "user" (user_handle)');

        // 2. One row per registered authenticator. FK cascades so revoking a User drops its passkeys.
        $this->addSql('CREATE TABLE passkey_credential (id UUID NOT NULL, public_key_credential_id TEXT NOT NULL, type VARCHAR(32) NOT NULL, transports JSON NOT NULL, attestation_type VARCHAR(32) NOT NULL, trust_path JSON NOT NULL, aaguid TEXT NOT NULL, credential_public_key TEXT NOT NULL, user_handle UUID NOT NULL, counter BIGINT NOT NULL, other_ui JSON DEFAULT NULL, backup_eligible BOOLEAN DEFAULT NULL, backup_status BOOLEAN DEFAULT NULL, uv_initialized BOOLEAN DEFAULT NULL, name VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_used_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, user_agent_at_registration VARCHAR(255) DEFAULT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_DFD64A4572A8BD77 ON passkey_credential (public_key_credential_id)');
        $this->addSql('CREATE INDEX idx_passkey_user_handle ON passkey_credential (user_handle)');
        $this->addSql('CREATE INDEX idx_passkey_user ON passkey_credential (user_id)');
        $this->addSql('ALTER TABLE passkey_credential ADD CONSTRAINT FK_DFD64A45A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE passkey_credential DROP CONSTRAINT FK_DFD64A45A76ED395');
        $this->addSql('DROP TABLE passkey_credential');
        $this->addSql('DROP INDEX UNIQ_8D93D649F4D23BE4');
        $this->addSql('ALTER TABLE "user" DROP user_handle');
    }
}
