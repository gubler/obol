<?php

declare(strict_types=1);

namespace App\Tests\Unit\Factory;

use App\Enum\SubscriptionEventType;
use App\Factory\SubscriptionEventFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;

class SubscriptionEventFactoryTest extends KernelTestCase
{
    use Factories;

    public function testCreatesSubscriptionEventWithRequiredFields(): void
    {
        $event = SubscriptionEventFactory::createOne();

        $this->assertInstanceOf(SubscriptionEventType::class, $event->type);
        $this->assertInstanceOf(\App\Entity\Subscription::class, $event->subscription);
        $this->assertIsArray($event->context);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->createdAt);
    }

    public function testAllowsCustomContext(): void
    {
        $context = ['field' => ['old' => 'value1', 'new' => 'value2']];
        $event = SubscriptionEventFactory::createOne(['context' => $context]);

        $this->assertSame($context, $event->context);
    }

    public function testUpdateCreatesUpdateEventType(): void
    {
        $event = SubscriptionEventFactory::new()->update()->create();

        $this->assertSame(SubscriptionEventType::Update, $event->type);
    }

    public function testCostChangeCreatesCostChangeEventType(): void
    {
        $event = SubscriptionEventFactory::new()->costChange()->create();

        $this->assertSame(SubscriptionEventType::CostChange, $event->type);
    }

    public function testArchiveCreatesArchiveEventType(): void
    {
        $event = SubscriptionEventFactory::new()->archive()->create();

        $this->assertSame(SubscriptionEventType::Archive, $event->type);
    }

    public function testUnarchiveCreatesUnarchiveEventType(): void
    {
        $event = SubscriptionEventFactory::new()->unarchive()->create();

        $this->assertSame(SubscriptionEventType::Unarchive, $event->type);
    }
}
