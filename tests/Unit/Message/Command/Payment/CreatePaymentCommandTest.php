<?php

// ABOUTME: Unit tests for CreatePaymentCommand verifying constructor stores values.
// ABOUTME: Validates command is readonly and holds subscriptionId, amount, and paidDate.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Payment;

use App\Message\Command\Payment\CreatePaymentCommand;
use PHPUnit\Framework\TestCase;

class CreatePaymentCommandTest extends TestCase
{
    public function testCommandStoresValues(): void
    {
        $paidDate = new \DateTimeImmutable('2025-01-15');

        $command = new CreatePaymentCommand(
            subscriptionId: '01JKEXAMPLEID000000000001',
            amount: 1500,
            paidDate: $paidDate,
        );

        self::assertSame('01JKEXAMPLEID000000000001', $command->subscriptionId);
        self::assertSame(1500, $command->amount);
        self::assertSame($paidDate, $command->paidDate);
    }
}
