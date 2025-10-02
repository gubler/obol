<?php

// ABOUTME: Unit tests for Payment entity ensuring proper instantiation and state validation.
// ABOUTME: Tests verify valid payment creation, amount validation, and business invariants.

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

class PaymentTest extends TestCase
{
    private Subscription $subscription;

    protected function setUp(): void
    {
        $category = new Category(name: 'Test Category');
        $this->subscription = new Subscription(
            category: $category,
            name: 'Test Subscription',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1000,
        );
    }

    public function testCreatesPaymentWithValidData(): void
    {
        $payment = new Payment(
            subscription: $this->subscription,
            type: PaymentType::Verified,
            amount: 1000,
        );

        self::assertSame($this->subscription, $payment->subscription);
        self::assertSame(PaymentType::Verified, $payment->type);
        self::assertSame(1000, $payment->amount);
    }

    public function testGeneratesUlidOnCreation(): void
    {
        $payment = new Payment(
            subscription: $this->subscription,
            type: PaymentType::Verified,
            amount: 500,
        );

        self::assertInstanceOf(Ulid::class, $payment->id);
    }

    public function testSetsCreatedAtToCurrentTime(): void
    {
        $before = new \DateTimeImmutable();
        $payment = new Payment(
            subscription: $this->subscription,
            type: PaymentType::Generated,
            amount: 2000,
        );
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $payment->createdAt);
        self::assertLessThanOrEqual($after, $payment->createdAt);
    }

    public function testAcceptsBothPaymentTypes(): void
    {
        $verifiedPayment = new Payment(
            subscription: $this->subscription,
            type: PaymentType::Verified,
            amount: 1000,
        );

        $generatedPayment = new Payment(
            subscription: $this->subscription,
            type: PaymentType::Generated,
            amount: 1000,
        );

        self::assertSame(PaymentType::Verified, $verifiedPayment->type);
        self::assertSame(PaymentType::Generated, $generatedPayment->type);
    }

    public function testRejectsZeroAmount(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Payment(
            subscription: $this->subscription,
            type: PaymentType::Verified,
            amount: 0,
        );
    }

    public function testRejectsNegativeAmount(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Payment(
            subscription: $this->subscription,
            type: PaymentType::Verified,
            amount: -100,
        );
    }
}
