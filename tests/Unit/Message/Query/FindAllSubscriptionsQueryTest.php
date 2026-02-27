<?php

// ABOUTME: Unit tests for FindAllSubscriptionsQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates without parameters and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query;

use App\Message\Query\Subscription\FindAllSubscriptionsQuery;
use PHPUnit\Framework\TestCase;

class FindAllSubscriptionsQueryTest extends TestCase
{
    public function testIsReadonly(): void
    {
        $query = new FindAllSubscriptionsQuery();

        $reflection = new \ReflectionClass($query);
        self::assertTrue($reflection->isReadOnly());
    }
}
