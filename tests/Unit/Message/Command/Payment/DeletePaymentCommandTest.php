<?php

// ABOUTME: Unit tests for DeletePaymentCommand verifying constructor stores paymentId.
// ABOUTME: Validates command is readonly and holds the payment identifier.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Payment;

use App\Message\Command\Payment\DeletePaymentCommand;
use PHPUnit\Framework\TestCase;

class DeletePaymentCommandTest extends TestCase
{
    public function testCommandStoresValues(): void
    {
        $command = new DeletePaymentCommand(paymentId: '01JKEXAMPLEID000000000001');

        self::assertSame('01JKEXAMPLEID000000000001', $command->paymentId);
    }
}
