<?php

// ABOUTME: Unit tests for AmendPaymentHandler verifying payment amendment via the entity.
// ABOUTME: Tests the happy path (amend) and the not-found branch.

declare(strict_types=1);

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Message\Command\Payment\AmendPaymentCommand;
use App\Message\Command\Payment\AmendPaymentHandler;
use App\Repository\PaymentRepository;
use App\ValueObject\Money;
use Symfony\Component\Uid\Ulid;

test('amends the payment', function (): void {
    $subscription = new Subscription(
        category: new Category(name: 'Test'),
        name: 'Netflix',
        nextRenewal: new DateTimeImmutable('2024-02-01'),
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: new Money(1000, Currency::USD),
    );
    $payment = new Payment(
        subscription: $subscription,
        type: PaymentType::Generated,
        amount: new Money(1000, Currency::USD),
        paidDate: new DateTimeImmutable('2024-01-01'),
    );

    $repository = $this->createMock(PaymentRepository::class);
    $repository->expects($this->once())->method('find')->willReturn($payment);

    $handler = new AmendPaymentHandler($repository);
    $handler(new AmendPaymentCommand(
        paymentId: $payment->id,
        amount: 1200,
        paidDate: new DateTimeImmutable('2024-01-05'),
    ));

    expect($payment->amount->minorAmount)->toBe(1200)
        ->and($payment->paidDate)->toEqual(new DateTimeImmutable('2024-01-05'))
        ->and($payment->type)->toBe(PaymentType::Verified)
    ;
});

test('throws when payment not found', function (): void {
    $repository = $this->createMock(PaymentRepository::class);
    $repository->expects($this->once())->method('find')->willReturn(null);

    $handler = new AmendPaymentHandler($repository);

    $handler(new AmendPaymentCommand(
        paymentId: new Ulid(),
        amount: 1200,
        paidDate: new DateTimeImmutable(),
    ));
})->throws(InvalidArgumentException::class);
