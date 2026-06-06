<?php

// ABOUTME: Unit tests for AmendPaymentCommand verifying constructor stores values.
// ABOUTME: Validates the command holds paymentId, amount, and paidDate.

declare(strict_types=1);

use App\Message\Command\Payment\AmendPaymentCommand;
use Symfony\Component\Uid\Ulid;

test('command stores values', function (): void {
    $paymentId = new Ulid();
    $paidDate = new DateTimeImmutable('2024-01-05');

    $command = new AmendPaymentCommand(
        paymentId: $paymentId,
        amount: 1200,
        paidDate: $paidDate,
    );

    expect($command->paymentId)->toBe($paymentId)
        ->and($command->amount)->toBe(1200)
        ->and($command->paidDate)->toBe($paidDate)
    ;
});
