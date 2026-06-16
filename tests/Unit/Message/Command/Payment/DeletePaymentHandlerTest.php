<?php

// ABOUTME: Unit tests for DeletePaymentHandler verifying payment removal and renewal rollback.
// ABOUTME: Removing a payment deletes it (orphan removal) and pulls the subscription's anchor back one interval.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Payment;

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentGeneration;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Message\Command\Payment\DeletePaymentCommand;
use App\Message\Command\Payment\DeletePaymentHandler;
use App\Repository\PaymentRepository;
use App\Tests\Support\InstantAssertions;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class DeletePaymentHandlerTest extends TestCase
{
    use InstantAssertions;

    public function testRemovingAGeneratedPaymentRollsBackTheAnchorAndSwitchesToManualGeneration(): void
    {
        $subscription = new Subscription(
            category: new Category(name: 'Test'),
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );
        // A scheduler-generated due payment advances the anchor to 2024-03-01.
        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Generated,
        );
        /** @var Payment $payment */
        $payment = $subscription->payments->first();

        $repository = $this->createMock(PaymentRepository::class);
        $repository->expects(self::once())->method('find')->willReturn($payment);

        $handler = new DeletePaymentHandler($repository);
        $handler(new DeletePaymentCommand(paymentId: $payment->id));

        self::assertCount(0, $subscription->payments);
        self::assertSameInstant(new \DateTimeImmutable('2024-02-01'), $subscription->nextRenewal);
        self::assertSame(PaymentGeneration::Manual, $subscription->paymentGeneration);
    }

    public function testRemovingAVerifiedPaymentRollsBackTheAnchorButLeavesGenerationAutomated(): void
    {
        $subscription = new Subscription(
            category: new Category(name: 'Test'),
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );
        // A user-recorded current-period payment advances the anchor to 2024-03-01.
        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-01-15'),
            paymentType: PaymentType::Verified,
        );
        /** @var Payment $payment */
        $payment = $subscription->payments->first();

        $repository = $this->createMock(PaymentRepository::class);
        $repository->expects(self::once())->method('find')->willReturn($payment);

        $handler = new DeletePaymentHandler($repository);
        $handler(new DeletePaymentCommand(paymentId: $payment->id));

        self::assertCount(0, $subscription->payments);
        self::assertSameInstant(new \DateTimeImmutable('2024-02-01'), $subscription->nextRenewal);
        self::assertSame(PaymentGeneration::Automated, $subscription->paymentGeneration);
    }

    public function testThrowsWhenPaymentNotFound(): void
    {
        $repository = $this->createMock(PaymentRepository::class);
        $repository->expects(self::once())->method('find')->willReturn(null);

        $handler = new DeletePaymentHandler($repository);

        $this->expectException(\InvalidArgumentException::class);

        $handler(new DeletePaymentCommand(paymentId: new Ulid()));
    }
}
