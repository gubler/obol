<?php

// ABOUTME: Unit tests for AmendPaymentCommand verifying constructor stores values.
// ABOUTME: Validates the command holds paymentId, amount, and paidDate.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Payment;

use App\Message\Command\Payment\AmendPaymentCommand;
use App\ValueObject\CalendarDate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class AmendPaymentCommandTest extends TestCase
{
    public function testCommandStoresValues(): void
    {
        $paymentId = new Ulid();
        $paidDate = CalendarDate::fromString('2024-01-05');

        $command = new AmendPaymentCommand(
            ownerUserId: new Ulid(),
            paymentId: $paymentId,
            amount: 1200,
            paidDate: $paidDate,
        );

        self::assertSame($paymentId, $command->paymentId);
        self::assertSame(1200, $command->amount);
        self::assertSame($paidDate, $command->paidDate);
    }
}
