<?php

// ABOUTME: Unit tests for PaymentFactory ensuring proper factory defaults and state methods.
// ABOUTME: Tests verify payment creation, custom amounts, and payment type state methods.

declare(strict_types=1);

use App\Enum\Currency;
use App\Enum\PaymentType;
use App\Factory\PaymentFactory;
use App\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

uses(KernelTestCase::class);

test('creates payment with required fields', function (): void {
    $payment = PaymentFactory::createOne();

    expect($payment->amount->minorAmount)->toBeGreaterThan(0);
});

test('allows custom amount', function (): void {
    $payment = PaymentFactory::createOne(['amount' => new Money(1999, Currency::USD)]);

    expect($payment->amount->minorAmount)->toBe(1999);
});

test('regular creates verified payment', function (): void {
    $payment = PaymentFactory::new()->regular()->create();

    expect($payment->type)->toBe(PaymentType::Verified);
});

test('generated creates generated payment', function (): void {
    $payment = PaymentFactory::new()->generated()->create();

    expect($payment->type)->toBe(PaymentType::Generated);
});
