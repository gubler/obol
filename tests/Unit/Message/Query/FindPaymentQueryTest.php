<?php

// ABOUTME: Unit tests for FindPaymentQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates with payment ID and maintains readonly properties.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query;

use App\Message\Query\Payment\FindPaymentQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class FindPaymentQueryTest extends TestCase
{
    public function testCreatesQueryWithPaymentId(): void
    {
        $paymentId = new Ulid();
        $query = new FindPaymentQuery(ownerUserId: new Ulid(), paymentId: $paymentId);

        self::assertSame($paymentId, $query->paymentId);
    }

    public function testIsReadonly(): void
    {
        $query = new FindPaymentQuery(
            ownerUserId: new Ulid(),
            paymentId: new Ulid()
        );

        $reflection = new \ReflectionClass($query);
        self::assertTrue($reflection->isReadOnly());
    }
}
