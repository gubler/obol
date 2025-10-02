<?php

declare(strict_types=1);

namespace App\Tests\Unit\Factory;

use App\Enum\PaymentPeriod;
use App\Factory\SubscriptionFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

class SubscriptionFactoryTest extends KernelTestCase
{
    use Factories;

    public function testCreatesSubscriptionWithRequiredFields(): void
    {
        $subscription = SubscriptionFactory::createOne();

        $this->assertIsString($subscription->name);
        $this->assertNotEmpty($subscription->name);
        $this->assertInstanceOf(\App\Entity\Category::class, $subscription->category);
        $this->assertIsInt($subscription->cost);
        $this->assertGreaterThan(0, $subscription->cost);
        $this->assertInstanceOf(PaymentPeriod::class, $subscription->paymentPeriod);
        $this->assertIsInt($subscription->paymentPeriodCount);
        $this->assertGreaterThan(0, $subscription->paymentPeriodCount);
        $this->assertInstanceOf(\DateTimeImmutable::class, $subscription->lastPaidDate);
        $this->assertFalse($subscription->archived);
        $this->assertCount(0, $subscription->payments);
        $this->assertCount(0, $subscription->subscriptionEvents);
    }

    public function testAllowsCustomName(): void
    {
        $subscription = SubscriptionFactory::createOne(['name' => 'Netflix']);

        $this->assertSame('Netflix', $subscription->name);
    }

    public function testArchivedCreatesArchivedSubscription(): void
    {
        $subscription = SubscriptionFactory::new()->archived()->create();

        $this->assertTrue($subscription->archived);
    }

    public function testWithRecentPaymentCreatesSubscriptionWithRecentPayment(): void
    {
        $subscription = SubscriptionFactory::new()->withRecentPayment()->create();

        $this->assertCount(1, $subscription->payments);
        $this->assertInstanceOf(\DateTimeImmutable::class, $subscription->payments->first()->createdAt);
    }

    public function testExpensiveSubscriptionCreatesSubscriptionWithHighCost(): void
    {
        $subscription = SubscriptionFactory::new()->expensiveSubscription()->create();

        $this->assertGreaterThanOrEqual(5000, $subscription->cost);
    }
}
