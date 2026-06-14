<?php

// ABOUTME: Unit tests for FindPaymentRunner verifying payment lookup by ID.
// ABOUTME: Tests valid lookup returns the payment and not-found returns null.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query;

use App\Entity\Payment;
use App\Message\Query\Payment\FindPaymentQuery;
use App\Message\Query\Payment\FindPaymentRunner;
use App\Repository\PaymentRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class FindPaymentRunnerTest extends TestCase
{
    public function testReturnsPaymentWhenFound(): void
    {
        $ulid = new Ulid();
        $payment = $this->createMock(Payment::class);

        $repository = $this->createMock(PaymentRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn($payment)
        ;

        $runner = new FindPaymentRunner($repository);
        $result = $runner(new FindPaymentQuery(paymentId: $ulid));

        self::assertSame($payment, $result);
    }

    public function testReturnsNullWhenNotFound(): void
    {
        $ulid = new Ulid();

        $repository = $this->createMock(PaymentRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn(null)
        ;

        $runner = new FindPaymentRunner($repository);
        $result = $runner(new FindPaymentQuery(paymentId: $ulid));

        self::assertNull($result);
    }
}
