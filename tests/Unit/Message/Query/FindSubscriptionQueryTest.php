<?php

// ABOUTME: Unit tests for FindSubscriptionQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates with subscription ID and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query;

use App\Message\Query\Subscription\FindSubscriptionQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class FindSubscriptionQueryTest extends TestCase
{
    public function testCreatesQueryWithSubscriptionId(): void
    {
        $subscriptionId = new Ulid();
        $query = new FindSubscriptionQuery(ownerUserId: new Ulid(), subscriptionId: $subscriptionId);

        self::assertSame($subscriptionId, $query->subscriptionId);
    }

    public function testIsReadonly(): void
    {
        $query = new FindSubscriptionQuery(
            ownerUserId: new Ulid(),
            subscriptionId: new Ulid()
        );

        $reflection = new \ReflectionClass($query);
        self::assertTrue($reflection->isReadOnly());
    }
}
