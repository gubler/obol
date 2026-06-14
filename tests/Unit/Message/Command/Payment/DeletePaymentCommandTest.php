<?php

// ABOUTME: Unit tests for DeletePaymentCommand verifying constructor stores paymentId.
// ABOUTME: Validates command holds the payment identifier.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Payment;

use App\Message\Command\Payment\DeletePaymentCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class DeletePaymentCommandTest extends TestCase
{
    public function testCommandStoresValues(): void
    {
        $paymentId = new Ulid();
        $command = new DeletePaymentCommand(paymentId: $paymentId);

        self::assertSame($paymentId, $command->paymentId);
    }
}
