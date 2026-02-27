<?php

// ABOUTME: Unit tests for Payment entity ensuring proper instantiation and state validation.
// ABOUTME: Tests verify valid payment creation, amount validation, and business invariants.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;

beforeEach(function (): void {
    $category = new Category(name: 'Test Category');
    $this->subscription = new Subscription(
        category: $category,
        name: 'Test Subscription',
        lastPaidDate: new DateTimeImmutable(),
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: 1000,
    );
});

test('creates payment with valid data', function (): void {
    $payment = new Payment(
        subscription: $this->subscription,
        type: PaymentType::Verified,
        amount: 1000,
    );

    expect($payment->subscription)->toBe($this->subscription)
        ->and($payment->type)->toBe(PaymentType::Verified)
        ->and($payment->amount)->toBe(1000)
    ;
});

test('sets created at to current time', function (): void {
    $before = new DateTimeImmutable();
    $payment = new Payment(
        subscription: $this->subscription,
        type: PaymentType::Generated,
        amount: 2000,
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
        amount: 1000,
    );

    $generatedPayment = new Payment(
        subscription: $this->subscription,
        type: PaymentType::Generated,
        amount: 1000,
    );

    expect($verifiedPayment->type)->toBe(PaymentType::Verified)
        ->and($generatedPayment->type)->toBe(PaymentType::Generated)
    ;
});

test('rejects zero amount', function (): void {
    new Payment(
        subscription: $this->subscription,
        type: PaymentType::Verified,
        amount: 0,
    );
})->throws(Assert\InvalidArgumentException::class);

test('rejects negative amount', function (): void {
    new Payment(
        subscription: $this->subscription,
        type: PaymentType::Verified,
        amount: -100,
    );
})->throws(Assert\InvalidArgumentException::class);
