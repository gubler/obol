<?php

// ABOUTME: Unit tests for AmendPaymentHandler verifying payment amendment via the entity.
// ABOUTME: Tests the happy path (amend) and the not-found branch.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Payment;

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Message\Command\Payment\AmendPaymentCommand;
use App\Message\Command\Payment\AmendPaymentHandler;
use App\Repository\PaymentRepository;
use App\Tests\Support\InstantAssertions;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class AmendPaymentHandlerTest extends TestCase
{
    use InstantAssertions;

    public function testAmendsThePayment(): void
    {
        $subscription = new Subscription(
            owner: new User(email: 'owner@example.com'),
            category: new Category(name: 'Test'),
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1000, Currency::USD),
        );
        $payment = new Payment(
            subscription: $subscription,
            type: PaymentType::Generated,
            amount: new Money(1000, Currency::USD),
            paidDate: new \DateTimeImmutable('2024-01-01'),
        );

        $repository = $this->createMock(PaymentRepository::class);
        $repository->expects(self::once())->method('findForOwner')->willReturn($payment);

        $handler = new AmendPaymentHandler($repository);
        $handler(new AmendPaymentCommand(
            ownerUserId: new Ulid(),
            paymentId: $payment->id,
            amount: 1200,
            paidDate: new \DateTimeImmutable('2024-01-05'),
        ));

        self::assertSame(1200, $payment->amount->minorAmount);
        self::assertSameInstant(new \DateTimeImmutable('2024-01-05'), $payment->paidDate);
        self::assertSame(PaymentType::Verified, $payment->type);
    }

    public function testThrowsWhenPaymentNotFound(): void
    {
        $repository = $this->createMock(PaymentRepository::class);
        $repository->expects(self::once())->method('findForOwner')->willReturn(null);

        $handler = new AmendPaymentHandler($repository);

        $this->expectException(\InvalidArgumentException::class);

        $handler(new AmendPaymentCommand(
            ownerUserId: new Ulid(),
            paymentId: new Ulid(),
            amount: 1200,
            paidDate: new \DateTimeImmutable(),
        ));
    }
}
