<?php

// ABOUTME: Unit tests for Payment entity ensuring proper instantiation and state validation.
// ABOUTME: Tests verify valid payment creation, amount validation, and business invariants.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\ValueObject\Money;

beforeEach(function (): void {
    $category = new Category(name: 'Test Category');
    $this->subscription = new Subscription(
        category: $category,
        name: 'Test Subscription',
        nextRenewal: new DateTimeImmutable(),
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: new Money(1000, Currency::USD),
    );
});

test('creates payment with valid data', function (): void {
    $payment = new Payment(
        subscription: $this->subscription,
        type: PaymentType::Verified,
        amount: new Money(1000, Currency::USD),
    );

    expect($payment->subscription)->toBe($this->subscription)
        ->and($payment->type)->toBe(PaymentType::Verified)
        ->and($payment->amount->minorAmount)->toBe(1000)
    ;
});

test('stores the paid date', function (): void {
    $paidDate = new DateTimeImmutable('2024-05-01');
    $payment = new Payment(
        subscription: $this->subscription,
        type: PaymentType::Verified,
        amount: new Money(1000, Currency::USD),
        paidDate: $paidDate,
    );

    expect($payment->paidDate)->toBe($paidDate);
});

test('sets created at to current time', function (): void {
    $before = new DateTimeImmutable();
    $payment = new Payment(
        subscription: $this->subscription,
        type: PaymentType::Generated,
        amount: new Money(2000, Currency::USD),
    );
    $after = new DateTimeImmutable();

    expect($payment->createdAt)->toBeGreaterThanOrEqual($before)
        ->and($payment->createdAt)->toBeLessThanOrEqual($after)
    ;
});

test('accepts both payment types', function (): void {
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

    expect($verifiedPayment->type)->toBe(PaymentType::Verified)
        ->and($generatedPayment->type)->toBe(PaymentType::Generated)
    ;
});

test('amend updates amount and paid date and verifies the payment', function (): void {
    $payment = new Payment(
        subscription: $this->subscription,
        type: PaymentType::Generated,
        amount: new Money(1000, Currency::USD),
        paidDate: new DateTimeImmutable('2024-01-01'),
    );

    $payment->amend(amount: 1200, paidDate: new DateTimeImmutable('2024-01-05'));

    expect($payment->amount->minorAmount)->toBe(1200)
        ->and($payment->paidDate)->toEqual(new DateTimeImmutable('2024-01-05'))
        ->and($payment->type)->toBe(PaymentType::Verified)
    ;
});

test('amend rejects a non-positive amount', function (): void {
    $payment = new Payment(
        subscription: $this->subscription,
        type: PaymentType::Generated,
        amount: new Money(1000, Currency::USD),
    );

    $payment->amend(amount: 0, paidDate: new DateTimeImmutable());
})->throws(Assert\InvalidArgumentException::class);

test('rejects zero amount', function (): void {
    new Payment(
        subscription: $this->subscription,
        type: PaymentType::Verified,
        amount: new Money(0, Currency::USD),
    );
})->throws(Assert\InvalidArgumentException::class);

test('rejects negative amount', function (): void {
    new Payment(
        subscription: $this->subscription,
        type: PaymentType::Verified,
        amount: new Money(-100, Currency::USD),
    );
})->throws(Assert\InvalidArgumentException::class);
