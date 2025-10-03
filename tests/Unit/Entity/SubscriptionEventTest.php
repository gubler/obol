<?php

// ABOUTME: Unit tests for SubscriptionEvent entity ensuring proper instantiation and state validation.
// ABOUTME: Tests verify event creation for all types, context structure, and business invariants.

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Entity\SubscriptionEvent;
use App\Enum\PaymentPeriod;
use App\Enum\SubscriptionEventType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

class SubscriptionEventTest extends TestCase
{
    private Subscription $subscription;

    protected function setUp(): void
    {
        $category = new Category(name: 'Test Category');
        $this->subscription = new Subscription(
            category: $category,
            name: 'Test Subscription',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1000,
        );
    }

    public function testCreatesUpdateEvent(): void
    {
        $context = ['changes' => ['name' => 'New Name']];
        $event = new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::Update,
            context: $context,
        );

        self::assertSame($this->subscription, $event->subscription);
        self::assertSame(SubscriptionEventType::Update, $event->type);
        self::assertSame($context, $event->context);
    }

    public function testCreatesCostChangeEvent(): void
    {
        $context = ['old' => ['cost' => 1000], 'new' => ['cost' => 1500]];
        $event = new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::CostChange,
            context: $context,
        );

        self::assertSame(SubscriptionEventType::CostChange, $event->type);
        self::assertSame($context, $event->context);
    }

    public function testCreatesArchiveEvent(): void
    {
        $context = [];
        $event = new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::Archive,
            context: $context,
        );

        self::assertSame(SubscriptionEventType::Archive, $event->type);
        self::assertSame($context, $event->context);
    }

    public function testCreatesUnarchiveEvent(): void
    {
        $context = [];
        $event = new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::Unarchive,
            context: $context,
        );

        self::assertSame(SubscriptionEventType::Unarchive, $event->type);
        self::assertSame($context, $event->context);
    }

    public function testSetsCreatedAtToCurrentTime(): void
    {
        $before = new \DateTimeImmutable();
        $event = new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::Update,
            context: ['changes' => ['name' => 'Test']],
        );
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $event->createdAt);
        self::assertLessThanOrEqual($after, $event->createdAt);
    }

    public function testAcceptsComplexContextStructure(): void
    {
        $context = [
            'old' => ['cost' => 1000, 'period' => 'month'],
            'new' => ['cost' => 1500, 'period' => 'year'],
        ];
        $event = new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::CostChange,
            context: $context,
        );

        self::assertSame($context, $event->context);
        self::assertArrayHasKey('old', $event->context);
        self::assertArrayHasKey('new', $event->context);
    }

    public function testRejectsNonEmptyContextForArchiveEvent(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::Archive,
            context: ['new' => ['some' => 'data']],
        );
    }

    public function testRejectsNonEmptyContextForUnarchiveEvent(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::Unarchive,
            context: ['new' => ['some' => 'data']],
        );
    }

    public function testRejectsEmptyContextForUpdateEvent(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::Update,
            context: [],
        );
    }

    public function testRejectsEmptyContextForCostChangeEvent(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new SubscriptionEvent(
            subscription: $this->subscription,
            type: SubscriptionEventType::CostChange,
            context: [],
        );
    }
}
