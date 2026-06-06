<?php

// ABOUTME: Unit tests for DeletePaymentCommand verifying constructor stores paymentId.
// ABOUTME: Validates command holds the payment identifier.

declare(strict_types=1);

use App\Message\Command\Payment\DeletePaymentCommand;
use Symfony\Component\Uid\Ulid;

test('command stores values', function (): void {
    $paymentId = new Ulid();
    $command = new DeletePaymentCommand(paymentId: $paymentId);

    expect($command->paymentId)->toBe($paymentId);
});
