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

        self::assertNotEmpty($subscription->name);
        self::assertGreaterThan(0, $subscription->cost);
        self::assertGreaterThan(0, $subscription->paymentPeriodCount);
        self::assertFalse($subscription->archived);
        self::assertCount(0, $subscription->payments);
        self::assertCount(0, $subscription->subscriptionEvents);
    }

    public function testAllowsCustomName(): void
    {
        $subscription = SubscriptionFactory::createOne(['name' => 'Netflix']);

        self::assertSame('Netflix', $subscription->name);
    }

    public function testArchivedCreatesArchivedSubscription(): void
    {
        $subscription = SubscriptionFactory::new()->archived()->create();

        self::assertTrue($subscription->archived);
    }

    public function testWithRecentPaymentCreatesSubscriptionWithRecentPayment(): void
    {
        $subscription = SubscriptionFactory::new()->withRecentPayment()->create();

        self::assertCount(1, $subscription->payments);
    }

    public function testExpensiveSubscriptionCreatesSubscriptionWithHighCost(): void
    {
        $subscription = SubscriptionFactory::new()->expensiveSubscription()->create();

        self::assertGreaterThanOrEqual(5000, $subscription->cost);
    }
}
