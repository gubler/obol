<?php

// ABOUTME: Unit tests for Payment entity ensuring proper instantiation and state validation.
// ABOUTME: Tests verify valid payment creation, amount validation, and business invariants.

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Tests\Support\InstantAssertions;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class PaymentTest extends TestCase
{
    use InstantAssertions;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $category = new Category(owner: new User(email: 'owner@example.com'), name: 'Test Category');
        $this->subscription = new Subscription(
            owner: new User(email: 'owner@example.com'),
            category: $category,
            name: 'Test Subscription',
            nextRenewal: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1000, Currency::USD),
        );
    }

    public function testCreatesPaymentWithValidData(): void
    {
        $payment = new Payment(
            subscription: $this->subscription,
            type: PaymentType::Verified,
            amount: new Money(1000, Currency::USD),
        );

        self::assertSame($this->subscription, $payment->subscription);
        self::assertSame(PaymentType::Verified, $payment->type);
        self::assertSame(1000, $payment->amount->minorAmount);
    }

    public function testDerivesItsOwnerFromItsSubscription(): void
    {
        $payment = new Payment(
            subscription: $this->subscription,
            type: PaymentType::Verified,
            amount: new Money(1000, Currency::USD),
        );

        // Denormalized copy-at-birth: a payment's owner always equals its subscription's owner,
        // enforced in the constructor so no caller can create a mismatch (see ADR-0015).
        self::assertSame($this->subscription->owner, $payment->owner);
    }

    public function testStoresThePaidDate(): void
    {
        $paidDate = new \DateTimeImmutable('2024-05-01');
        $payment = new Payment(
            subscription: $this->subscription,
            type: PaymentType::Verified,
            amount: new Money(1000, Currency::USD),
            paidDate: $paidDate,
        );

        self::assertSame($paidDate, $payment->paidDate);
    }

    public function testSetsCreatedAtToCurrentTime(): void
    {
        $before = new \DateTimeImmutable();
        $payment = new Payment(
            subscription: $this->subscription,
            type: PaymentType::Generated,
            amount: new Money(2000, Currency::USD),
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
            amount: new Money(1000, Currency::USD),
        );

        $generatedPayment = new Payment(
            subscription: $this->subscription,
            type: PaymentType::Generated,
            amount: new Money(1000, Currency::USD),
        );

        self::assertSame(PaymentType::Verified, $verifiedPayment->type);
        self::assertSame(PaymentType::Generated, $generatedPayment->type);
    }

    public function testAmendUpdatesAmountAndPaidDateAndVerifiesThePayment(): void
    {
        $payment = new Payment(
            subscription: $this->subscription,
            type: PaymentType::Generated,
            amount: new Money(1000, Currency::USD),
            paidDate: new \DateTimeImmutable('2024-01-01'),
        );

        $payment->amend(amount: 1200, paidDate: new \DateTimeImmutable('2024-01-05'));

        self::assertSame(1200, $payment->amount->minorAmount);
        self::assertSameInstant(new \DateTimeImmutable('2024-01-05'), $payment->paidDate);
        self::assertSame(PaymentType::Verified, $payment->type);
    }

    public function testAmendRejectsANonPositiveAmount(): void
    {
        $payment = new Payment(
            subscription: $this->subscription,
            type: PaymentType::Generated,
            amount: new Money(1000, Currency::USD),
        );

        $this->expectException(\Assert\InvalidArgumentException::class);

        $payment->amend(amount: 0, paidDate: new \DateTimeImmutable());
    }

    public function testRejectsZeroAmount(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Payment(
            subscription: $this->subscription,
            type: PaymentType::Verified,
            amount: new Money(0, Currency::USD),
        );
    }

    public function testRejectsNegativeAmount(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Payment(
            subscription: $this->subscription,
            type: PaymentType::Verified,
            amount: new Money(-100, Currency::USD),
        );
    }
}
