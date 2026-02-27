<?php

// ABOUTME: Unit tests for FindSubscriptionQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates with subscription ID and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query;

use App\Message\Query\Subscription\FindSubscriptionQuery;
use PHPUnit\Framework\TestCase;

class FindSubscriptionQueryTest extends TestCase
{
    public function testCreatesQueryWithSubscriptionId(): void
    {
        $subscriptionId = '01JKSUB1234567890ABCDEFGH';
        $query = new FindSubscriptionQuery(subscriptionId: $subscriptionId);

        self::assertSame($subscriptionId, $query->subscriptionId);
    }

    public function testIsReadonly(): void
    {
        $query = new FindSubscriptionQuery(
            subscriptionId: '01JKSUB1234567890ABCDEFGH'
        );

        $reflection = new \ReflectionClass($query);
        self::assertTrue($reflection->isReadOnly());
    }
}
