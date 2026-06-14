<?php

// ABOUTME: Unit tests for PaymentFactory ensuring proper factory defaults and state methods.
// ABOUTME: Tests verify payment creation, custom amounts, and payment type state methods.

declare(strict_types=1);

namespace App\Tests\Unit\Factory;

use App\Enum\Currency;
use App\Enum\PaymentType;
use App\Factory\PaymentFactory;
use App\ValueObject\Money;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PaymentFactoryTest extends KernelTestCase
{
    public function testCreatesPaymentWithRequiredFields(): void
    {
        $payment = PaymentFactory::createOne();

        self::assertGreaterThan(0, $payment->amount->minorAmount);
    }

    public function testAllowsCustomAmount(): void
    {
        $payment = PaymentFactory::createOne(['amount' => new Money(1999, Currency::USD)]);

        self::assertSame(1999, $payment->amount->minorAmount);
    }

    public function testRegularCreatesVerifiedPayment(): void
    {
        $payment = PaymentFactory::new()->regular()->create();

        self::assertSame(PaymentType::Verified, $payment->type);
    }

    public function testGeneratedCreatesGeneratedPayment(): void
    {
        $payment = PaymentFactory::new()->generated()->create();

        self::assertSame(PaymentType::Generated, $payment->type);
    }
}
