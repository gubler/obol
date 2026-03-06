<?php

// ABOUTME: Unit tests for FindPaymentQuery ensuring proper instantiation and immutability.
// ABOUTME: Tests verify query creates with payment ID and maintains readonly properties.

declare(strict_types=1);

use App\Message\Query\Payment\FindPaymentQuery;

test('creates query with payment id', function (): void {
    $paymentId = '01JKPAY1234567890ABCDEFGH';
    $query = new FindPaymentQuery(paymentId: $paymentId);

    expect($query->paymentId)->toBe($paymentId);
});

test('is readonly', function (): void {
    $query = new FindPaymentQuery(
        paymentId: '01JKPAY1234567890ABCDEFGH'
    );

    $reflection = new ReflectionClass($query);
    expect($reflection->isReadOnly())->toBeTrue();
});
