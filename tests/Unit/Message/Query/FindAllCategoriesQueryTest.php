<?php

// ABOUTME: Unit tests for FindAllCategoriesQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates without parameters and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query;

use App\Message\Query\Category\FindAllCategoriesQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class FindAllCategoriesQueryTest extends TestCase
{
    public function testCarriesTheOwner(): void
    {
        $ownerUserId = new Ulid();
        $query = new FindAllCategoriesQuery(ownerUserId: $ownerUserId);

        self::assertSame($ownerUserId, $query->ownerUserId);
    }

    public function testIsReadonly(): void
    {
        $query = new FindAllCategoriesQuery(ownerUserId: new Ulid());

        $reflection = new \ReflectionClass($query);
        self::assertTrue($reflection->isReadOnly());
    }
}
