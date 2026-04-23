<?php

// ABOUTME: Unit tests for FindPaymentRunner verifying payment lookup by ID.
// ABOUTME: Tests valid ULID lookup, invalid ULID returns null, and not-found returns null.

declare(strict_types=1);

use App\Entity\Payment;
use App\Message\Query\Payment\FindPaymentQuery;
use App\Message\Query\Payment\FindPaymentRunner;
use App\Repository\PaymentRepository;
use Symfony\Component\Uid\Ulid;

test('returns payment when found', function (): void {
    $ulid = new Ulid();
    $payment = $this->createMock(Payment::class);

    $repository = $this->createMock(PaymentRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn($payment)
    ;

    $runner = new FindPaymentRunner($repository);
    $result = $runner(new FindPaymentQuery(paymentId: (string) $ulid));

    expect($result)->toBe($payment);
});

test('returns null when not found', function (): void {
    $ulid = new Ulid();

    $repository = $this->createMock(PaymentRepository::class);
    $repository->expects($this->once())
        ->method('find')
        ->willReturn(null)
    ;

    $runner = new FindPaymentRunner($repository);
    $result = $runner(new FindPaymentQuery(paymentId: (string) $ulid));

    expect($result)->toBeNull();
});

test('returns null for invalid ulid', function (): void {
    $repository = $this->createMock(PaymentRepository::class);
    $repository->expects($this->never())->method('find');

    $runner = new FindPaymentRunner($repository);
    $result = $runner(new FindPaymentQuery(paymentId: 'not-a-valid-ulid'));

    expect($result)->toBeNull();
});
