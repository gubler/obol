<?php

// ABOUTME: Unit tests for FindCategoryQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates with category ID and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query;

use App\Message\Query\Category\FindCategoryQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class FindCategoryQueryTest extends TestCase
{
    public function testCreatesQueryWithOwnerAndCategoryId(): void
    {
        $ownerUserId = new Ulid();
        $categoryId = new Ulid();
        $query = new FindCategoryQuery(ownerUserId: $ownerUserId, categoryId: $categoryId);

        self::assertSame($ownerUserId, $query->ownerUserId);
        self::assertSame($categoryId, $query->categoryId);
    }

    public function testIsReadonly(): void
    {
        $query = new FindCategoryQuery(
            ownerUserId: new Ulid(),
            categoryId: new Ulid()
        );

        $reflection = new \ReflectionClass($query);
        self::assertTrue($reflection->isReadOnly());
    }
}
