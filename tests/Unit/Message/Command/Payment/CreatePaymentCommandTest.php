<?php

// ABOUTME: Unit tests for CreatePaymentCommand verifying constructor stores values.
// ABOUTME: Validates command holds subscriptionId, amount, and paidDate.

declare(strict_types=1);

use App\Message\Command\Payment\CreatePaymentCommand;
use Symfony\Component\Uid\Ulid;

test('command stores values', function (): void {
    $subscriptionId = new Ulid();
    $paidDate = new DateTimeImmutable('2025-01-15');

    $command = new CreatePaymentCommand(
        subscriptionId: $subscriptionId,
        amount: 1500,
        paidDate: $paidDate,
    );

    expect($command->subscriptionId)->toBe($subscriptionId)
        ->and($command->amount)->toBe(1500)
        ->and($command->paidDate)->toBe($paidDate)
    ;
});
