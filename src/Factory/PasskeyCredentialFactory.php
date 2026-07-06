<?php

// ABOUTME: Foundry factory for PasskeyCredential rows, for tests and fixtures.
// ABOUTME: A custom instantiator calls the real constructor (the WebAuthn CredentialRecord is not a mapped property, so property-based instantiation cannot build it).

declare(strict_types=1);

namespace App\Factory;

use App\Entity\PasskeyCredential;
use App\Entity\User;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<PasskeyCredential>
 */
final class PasskeyCredentialFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return PasskeyCredential::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'name' => self::faker()->words(2, true),
            'userAgentAtRegistration' => null,
        ];
    }

    #[\Override]
    protected function initialize(): static
    {
        // PasskeyCredential is constructed from a WebAuthn CredentialRecord, which it decomposes into
        // many private(set) columns - there is no `record` property for Foundry's default property-based
        // instantiation to set. So build it via the real constructor.
        return $this->instantiateWith(function (array $parameters): PasskeyCredential {
            $user = $parameters['user'];
            \assert($user instanceof User);
            $name = $parameters['name'];
            \assert(\is_string($name));
            $userAgent = $parameters['userAgentAtRegistration'] ?? null;

            return new PasskeyCredential(
                record: self::record(),
                user: $user,
                name: $name,
                userAgentAtRegistration: \is_string($userAgent) ? $userAgent : null,
            );
        });
    }

    /**
     * A synthetic CredentialRecord. Its userHandle is arbitrary here - the entity copies the owning
     * User's handle at construction - but publicKeyCredentialId must be unique across rows.
     */
    private static function record(): CredentialRecord
    {
        return new CredentialRecord(
            publicKeyCredentialId: random_bytes(16),
            type: 'public-key',
            transports: ['internal'],
            attestationType: 'none',
            trustPath: new EmptyTrustPath(),
            aaguid: Uuid::v4(),
            credentialPublicKey: random_bytes(32),
            userHandle: random_bytes(16),
            counter: 0,
        );
    }
}
