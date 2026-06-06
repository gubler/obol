<?php

// ABOUTME: Unit tests for FindPaymentQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates with payment ID and maintains readonly properties.

declare(strict_types=1);

use App\Message\Query\Payment\FindPaymentQuery;
use Symfony\Component\Uid\Ulid;

test('creates query with payment id', function (): void {
    $paymentId = new Ulid();
    $query = new FindPaymentQuery(paymentId: $paymentId);

    expect($query->paymentId)->toBe($paymentId);
});

test('is readonly', function (): void {
    $query = new FindPaymentQuery(
        paymentId: new Ulid()
    );

    $reflection = new ReflectionClass($query);
    expect($reflection->isReadOnly())->toBeTrue();
});
