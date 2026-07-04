<?php

// ABOUTME: Unit tests for FindPaymentSourceQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify the query creates with a payment source ID and stays readonly.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query;

use App\Message\Query\PaymentSource\FindPaymentSourceQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class FindPaymentSourceQueryTest extends TestCase
{
    public function testCreatesQueryWithOwnerAndPaymentSourceId(): void
    {
        $ownerUserId = new Ulid();
        $paymentSourceId = new Ulid();
        $query = new FindPaymentSourceQuery(ownerUserId: $ownerUserId, paymentSourceId: $paymentSourceId);

        self::assertSame($ownerUserId, $query->ownerUserId);
        self::assertSame($paymentSourceId, $query->paymentSourceId);
    }

    public function testIsReadonly(): void
    {
        $query = new FindPaymentSourceQuery(
            ownerUserId: new Ulid(),
            paymentSourceId: new Ulid()
        );

        $reflection = new \ReflectionClass($query);
        self::assertTrue($reflection->isReadOnly());
    }
}
