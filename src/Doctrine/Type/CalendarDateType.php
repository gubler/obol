<?php

// ABOUTME: Doctrine DBAL type persisting a CalendarDate value object as a single PostgreSQL DATE column.
// ABOUTME: A DATE (not a timestamp) keeps ORDER BY / DQL <= / AT_TIME_ZONE working on the naive calendar day. See ADR-0021.

declare(strict_types=1);

namespace App\Doctrine\Type;

use App\ValueObject\CalendarDate;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Type;

final class CalendarDateType extends Type
{
    public const string NAME = 'calendar_date';

    #[\Override]
    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getDateTypeDeclarationSQL($column);
    }

    #[\Override]
    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof CalendarDate) {
            return (string) $value;
        }

        throw InvalidType::new($value, self::class, ['null', CalendarDate::class]);
    }

    #[\Override]
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?CalendarDate
    {
        if (null === $value || $value instanceof CalendarDate) {
            return $value;
        }

        \assert(\is_string($value));

        // A DATE column yields a bare `Y-m-d`, but read defensively: take the leading calendar day and
        // drop any stray time part rather than reject it (a time is meaningless to a calendar date).
        return CalendarDate::fromString(substr($value, 0, 10));
    }
}
