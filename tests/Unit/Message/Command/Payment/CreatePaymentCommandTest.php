<?php

// ABOUTME: Unit tests for CreatePaymentCommand verifying constructor stores values.
// ABOUTME: Validates command holds subscriptionId, amount, and paidDate.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Payment;

use App\Message\Command\Payment\CreatePaymentCommand;
use App\ValueObject\CalendarDate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class CreatePaymentCommandTest extends TestCase
{
    public function testCommandStoresValues(): void
    {
        $subscriptionId = new Ulid();
        $paidDate = CalendarDate::fromString('2025-01-15');

        $command = new CreatePaymentCommand(
            ownerUserId: new Ulid(),
            subscriptionId: $subscriptionId,
            amount: 1500,
            paidDate: $paidDate,
        );

        self::assertSame($subscriptionId, $command->subscriptionId);
        self::assertSame(1500, $command->amount);
        self::assertSame($paidDate, $command->paidDate);
    }
}
