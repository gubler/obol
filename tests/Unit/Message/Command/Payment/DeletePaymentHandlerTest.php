<?php

// ABOUTME: Unit tests for DeletePaymentHandler verifying payment removal and renewal rollback.
// ABOUTME: Removing a payment deletes it (orphan removal) and pulls the subscription's anchor back one interval.

declare(strict_types=1);

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
use App\ValueObject\Money;
use Symfony\Component\Uid\Ulid;

test('removes the payment, rolls back the renewal anchor, and switches to manual generation', function (): void {
    $subscription = new Subscription(
        category: new Category(name: 'Test'),
        name: 'Netflix',
        nextRenewal: new DateTimeImmutable('2024-02-01'),
        paymentPeriod: PaymentPeriod::Month,
        paymentPeriodCount: 1,
        cost: new Money(1500, Currency::USD),
    );
    $subscription->recordPayment(
        paidDate: new DateTimeImmutable('2024-01-01'),
        paymentType: PaymentType::Verified,
    );
    /** @var Payment $payment */
    $payment = $subscription->payments->first();

    $repository = $this->createMock(PaymentRepository::class);
    $repository->expects($this->once())->method('find')->willReturn($payment);

    $handler = new DeletePaymentHandler($repository);
    $handler(new DeletePaymentCommand(paymentId: $payment->id));

    expect($subscription->payments)->toHaveCount(0)
        ->and($subscription->nextRenewal)->toEqual(new DateTimeImmutable('2024-02-01'))
        ->and($subscription->paymentGeneration)->toBe(PaymentGeneration::Manual)
    ;
});

test('throws when payment not found', function (): void {
    $repository = $this->createMock(PaymentRepository::class);
    $repository->expects($this->once())->method('find')->willReturn(null);

    $handler = new DeletePaymentHandler($repository);

    $handler(new DeletePaymentCommand(paymentId: new Ulid()));
})->throws(InvalidArgumentException::class);
