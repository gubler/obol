<?php

// ABOUTME: Per-User passkey credential row - one row per registered authenticator.
// ABOUTME: Stores the WebAuthn CredentialRecord shape plus app-side columns (name, lastUsedAt, UA at registration).

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PasskeyCredentialRepository;
use Assert\Assertion;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\TrustPath;

/**
 * Composition (not inheritance) over the bundle's CredentialRecord. This row stores the same column
 * shape and exposes {@see toCredentialRecord} for the bundle's hot paths. The TrustPath / aaguid /
 * base64 columns use the DBAL types the bundle registers via its prepend pass.
 */
#[ORM\Entity(repositoryClass: PasskeyCredentialRepository::class)]
#[ORM\Table(name: 'passkey_credential')]
#[ORM\Index(name: 'idx_passkey_user_handle', columns: ['user_handle'])]
#[ORM\Index(name: 'idx_passkey_user', columns: ['user_id'])]
class PasskeyCredential
{
    public const int NAME_MAX_LENGTH = 64;

    #[ORM\Id]
    #[ORM\Column(type: UlidType::NAME, unique: true)]
    public private(set) Ulid $id;

    /**
     * WebAuthn public-key credential ID - the authenticator-issued opaque identifier the browser
     * sends on every assertion. Unique across the table (one row per credential globally).
     */
    #[ORM\Column(type: 'base64', unique: true)]
    public private(set) string $publicKeyCredentialId;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 32)]
    public private(set) string $type;

    /** @var list<string> */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::JSON)]
    public private(set) array $transports;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 32)]
    public private(set) string $attestationType;

    #[ORM\Column(type: 'trust_path')]
    public private(set) TrustPath $trustPath;

    #[ORM\Column(type: 'aaguid', length: 36)]
    public private(set) Uuid $aaguid;

    #[ORM\Column(type: 'base64')]
    public private(set) string $credentialPublicKey;

    /**
     * Owner's stable WebAuthn userHandle - a copy of User.userHandle. Denormalised (no Doctrine
     * relation): the bundle's assertion hot path looks up by handle without the parent User loaded.
     */
    #[ORM\Column(type: UuidType::NAME)]
    public private(set) Uuid $userHandle;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BIGINT)]
    public private(set) int $counter;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::JSON, nullable: true)]
    public private(set) ?array $otherUI;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: true)]
    public private(set) ?bool $backupEligible;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: true)]
    public private(set) ?bool $backupStatus;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::BOOLEAN, nullable: true)]
    public private(set) ?bool $uvInitialized;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: self::NAME_MAX_LENGTH)]
    public private(set) string $name;

    #[ORM\Column]
    public private(set) \DateTimeImmutable $createdAt;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    public private(set) ?\DateTimeImmutable $lastUsedAt;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::STRING, length: 255, nullable: true)]
    public private(set) ?string $userAgentAtRegistration;

    public function __construct(
        CredentialRecord $record,
        /**
         * Direct owner FK. Distinct from {@see $userHandle}: this is the canonical "who owns this
         * credential" pointer for app-side owner-scoped finders; the handle stays alongside because the
         * WebAuthn assertion hot path resolves a credential without the parent User loaded.
         */
        #[ORM\ManyToOne(targetEntity: User::class)]
        #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
        public private(set) User $user,
        string $name,
        ?string $userAgentAtRegistration = null,
        ?\DateTimeImmutable $at = null,
    ) {
        $name = trim($name);
        Assertion::betweenLength(
            $name,
            1,
            self::NAME_MAX_LENGTH,
            \sprintf('Passkey name must be 1..%d chars.', self::NAME_MAX_LENGTH),
        );

        if (null !== $userAgentAtRegistration) {
            Assertion::maxLength(
                $userAgentAtRegistration,
                255,
                'Passkey userAgentAtRegistration must be at most 255 characters.',
            );
        }

        $this->id = new Ulid();
        $this->publicKeyCredentialId = $record->publicKeyCredentialId;
        $this->type = $record->type;
        $this->transports = array_values($record->transports);
        $this->attestationType = $record->attestationType;
        $this->trustPath = $record->trustPath;
        $this->aaguid = $record->aaguid;
        $this->credentialPublicKey = $record->credentialPublicKey;
        $this->userHandle = $this->user->userHandle;
        $this->counter = $record->counter;
        $this->otherUI = $record->otherUI;
        $this->backupEligible = $record->backupEligible;
        $this->backupStatus = $record->backupStatus;
        $this->uvInitialized = $record->uvInitialized;
        $this->name = $name;
        $this->createdAt = $at ?? new \DateTimeImmutable();
        $this->lastUsedAt = null;
        $this->userAgentAtRegistration = $userAgentAtRegistration;
    }

    /**
     * Rename the credential. Idempotent: returns true when the name actually changed, false when the
     * new name equals the current one (so callers can flash success only on a real change).
     */
    public function rename(string $name): bool
    {
        $name = trim($name);
        Assertion::betweenLength(
            $name,
            1,
            self::NAME_MAX_LENGTH,
            \sprintf('Passkey name must be 1..%d chars.', self::NAME_MAX_LENGTH),
        );

        if ($this->name === $name) {
            return false;
        }

        $this->name = $name;

        return true;
    }

    /**
     * Update bookkeeping after a successful assertion - the counter advances per the spec, lastUsedAt
     * feeds the management UI.
     */
    public function recordAssertion(int $counter, ?\DateTimeImmutable $at = null): void
    {
        $this->counter = $counter;
        $this->lastUsedAt = $at ?? new \DateTimeImmutable();
    }

    /**
     * Bundle-facing projection. The WebAuthn spec works with userHandle as raw bytes; this row stores
     * it as a UUID, so convert at the boundary.
     */
    public function toCredentialRecord(): CredentialRecord
    {
        return new CredentialRecord(
            publicKeyCredentialId: $this->publicKeyCredentialId,
            type: $this->type,
            transports: $this->transports,
            attestationType: $this->attestationType,
            trustPath: $this->trustPath,
            aaguid: $this->aaguid,
            credentialPublicKey: $this->credentialPublicKey,
            userHandle: $this->userHandle->toBinary(),
            counter: $this->counter,
            otherUI: $this->otherUI,
            backupEligible: $this->backupEligible,
            backupStatus: $this->backupStatus,
            uvInitialized: $this->uvInitialized,
        );
    }
}
