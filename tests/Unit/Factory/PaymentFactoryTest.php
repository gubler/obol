<?php

declare(strict_types=1);

namespace App\Tests\Unit\Factory;

use App\Enum\PaymentType;
use App\Factory\PaymentFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

class PaymentFactoryTest extends KernelTestCase
{
    use Factories;

    public function testCreatesPaymentWithRequiredFields(): void
    {
        $payment = PaymentFactory::createOne();

        $this->assertIsInt($payment->amount);
        $this->assertGreaterThan(0, $payment->amount);
        $this->assertInstanceOf(PaymentType::class, $payment->type);
        $this->assertInstanceOf(\App\Entity\Subscription::class, $payment->subscription);
        $this->assertInstanceOf(\DateTimeImmutable::class, $payment->createdAt);
    }

    public function testAllowsCustomAmount(): void
    {
        $payment = PaymentFactory::createOne(['amount' => 1999]);

        $this->assertSame(1999, $payment->amount);
    }

    public function testRegularCreatesVerifiedPayment(): void
    {
        $payment = PaymentFactory::new()->regular()->create();

        $this->assertSame(PaymentType::Verified, $payment->type);
    }

    public function testGeneratedCreatesGeneratedPayment(): void
    {
        $payment = PaymentFactory::new()->generated()->create();

        $this->assertSame(PaymentType::Generated, $payment->type);
    }
}
