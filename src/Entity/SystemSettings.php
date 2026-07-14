<?php

// ABOUTME: The app-global system settings singleton - operator-controlled configuration, not per-user.
// ABOUTME: A structural single row (smallint id fixed at 1, CHECK(id=1) in the migration); see ADR-0020.

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SystemSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemSettingsRepository::class)]
#[ORM\Table(name: 'system_settings')]
class SystemSettings
{
    // Fixed at 1 for every instance. The migration pins this with a CHECK (id = 1) constraint, so the
    // table can hold at most one row - the "there is exactly one" invariant lives in the schema, not a
    // convention. Not a ULID (ADR-0001): this row is never referenced by a foreign key or a URL.
    #[ORM\Id]
    #[ORM\Column(type: Types::SMALLINT)]
    public private(set) int $id = 1;

    public function __construct(
        #[ORM\Column]
        public private(set) bool $publicSignupEnabled = false,
    ) {
    }

    /**
     * Turn public self-registration on or off. The one intention-revealing mutator for this setting;
     * each future toggle gets its own typed method rather than a generic string-keyed setter.
     */
    public function changePublicSignup(bool $enabled): void
    {
        $this->publicSignupEnabled = $enabled;
    }
}
