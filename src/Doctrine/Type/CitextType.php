<?php

// ABOUTME: Doctrine DBAL type backed by PostgreSQL's citext extension.
// ABOUTME: Stores strings with case-insensitive comparison + uniqueness - used for User.email and UserEmail.email.

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

final class CitextType extends StringType
{
    public const string NAME = 'citext';

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'CITEXT';
    }
}
