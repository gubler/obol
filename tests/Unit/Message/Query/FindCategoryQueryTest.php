<?php

// ABOUTME: Unit tests for FindCategoryQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates with category ID and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query;

use App\Message\Query\Category\FindCategoryQuery;
use PHPUnit\Framework\TestCase;

class FindCategoryQueryTest extends TestCase
{
    public function testCreatesQueryWithCategoryId(): void
    {
        $categoryId = '01JBBQ7Z8Z8Z8Z8Z8Z8Z8Z8Z8Z';
        $query = new FindCategoryQuery(categoryId: $categoryId);

        self::assertSame($categoryId, $query->categoryId);
    }

    public function testIsReadonly(): void
    {
        $query = new FindCategoryQuery(
            categoryId: '01JBBQ7Z8Z8Z8Z8Z8Z8Z8Z8Z8Z'
        );

        $reflection = new \ReflectionClass($query);
        self::assertTrue($reflection->isReadOnly());
    }
}
