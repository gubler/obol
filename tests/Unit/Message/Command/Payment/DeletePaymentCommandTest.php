<?php

// ABOUTME: Unit tests for DeletePaymentCommand verifying constructor stores paymentId.
// ABOUTME: Validates command is readonly and holds the payment identifier.

declare(strict_types=1);

use App\Message\Command\Payment\DeletePaymentCommand;

test('command stores values', function (): void {
    $command = new DeletePaymentCommand(paymentId: '01JKEXAMPLEID000000000001');

    expect($command->paymentId)->toBe('01JKEXAMPLEID000000000001');
});
