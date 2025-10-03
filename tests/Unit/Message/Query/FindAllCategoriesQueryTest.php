<?php

// ABOUTME: Unit tests for FindAllCategoriesQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates without parameters and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query;

use App\Message\Query\FindAllCategoriesQuery;
use PHPUnit\Framework\TestCase;

class FindAllCategoriesQueryTest extends TestCase
{
    public function testIsReadonly(): void
    {
        $query = new FindAllCategoriesQuery();

        $reflection = new \ReflectionClass($query);
        self::assertTrue($reflection->isReadOnly());
    }
}