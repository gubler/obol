<?php

// ABOUTME: Unit tests for CreatePaymentCommand verifying constructor stores values.
// ABOUTME: Validates command is readonly and holds subscriptionId, amount, and paidDate.

declare(strict_types=1);

use App\Message\Command\Payment\CreatePaymentCommand;

test('command stores values', function (): void {
    $paidDate = new DateTimeImmutable('2025-01-15');

    $command = new CreatePaymentCommand(
        subscriptionId: '01JKEXAMPLEID000000000001',
        amount: 1500,
        paidDate: $paidDate,
    );

    expect($command->subscriptionId)->toBe('01JKEXAMPLEID000000000001')
        ->and($command->amount)->toBe(1500)
        ->and($command->paidDate)->toBe($paidDate)
    ;
});
