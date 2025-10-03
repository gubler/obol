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

    public function testAllowsCustomContext(): void
    {
        $context = ['field' => ['old' => 'value1', 'new' => 'value2']];
        $event = SubscriptionEventFactory::createOne(['type' => SubscriptionEventType::Update, 'context' => $context]);

        self::assertSame($context, $event->context);
    }

    public function testUpdateCreatesUpdateEventType(): void
    {
        $event = SubscriptionEventFactory::new()->update()->create();

        self::assertSame(SubscriptionEventType::Update, $event->type);
    }

    public function testCostChangeCreatesCostChangeEventType(): void
    {
        $event = SubscriptionEventFactory::new()->costChange()->create();

        self::assertSame(SubscriptionEventType::CostChange, $event->type);
    }

    public function testArchiveCreatesArchiveEventType(): void
    {
        $event = SubscriptionEventFactory::new()->archive()->create();

        self::assertSame(SubscriptionEventType::Archive, $event->type);
    }

    public function testUnarchiveCreatesUnarchiveEventType(): void
    {
        $event = SubscriptionEventFactory::new()->unarchive()->create();

        self::assertSame(SubscriptionEventType::Unarchive, $event->type);
    }
}
