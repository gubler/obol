<?php

// ABOUTME: Unit tests for Subscription entity ensuring proper instantiation and state validation.
// ABOUTME: Tests verify creation, update logic, payment recording, archival, and business invariants.

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\SubscriptionEvent;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Enum\SubscriptionEventType;
use Assert\Assertion;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

class SubscriptionTest extends TestCase
{
    private Category $category;

    protected function setUp(): void
    {
        $this->category = new Category(name: 'Entertainment');
    }

    public function testCreatesSubscriptionWithValidData(): void
    {
        $lastPaidDate = new \DateTimeImmutable('2024-01-01');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: $lastPaidDate,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        self::assertSame($this->category, $subscription->category);
        self::assertSame('Netflix', $subscription->name);
        self::assertSame($lastPaidDate, $subscription->lastPaidDate);
        self::assertSame(PaymentPeriod::Month, $subscription->paymentPeriod);
        self::assertSame(1, $subscription->paymentPeriodCount);
        self::assertSame(1500, $subscription->cost);
    }

    public function testSetsCreatedAtToCurrentTime(): void
    {
        $before = new \DateTimeImmutable();
        $subscription = new Subscription(
            category: $this->category,
            name: 'Spotify',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1000,
        );
        $after = new \DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $subscription->createdAt);
        self::assertLessThanOrEqual($after, $subscription->createdAt);
    }

    public function testInitializesAsNotArchived(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Spotify',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1000,
        );

        self::assertFalse($subscription->archived);
    }

    public function testInitializesEmptyCollections(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Spotify',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1000,
        );

        self::assertCount(0, $subscription->payments);
        self::assertCount(0, $subscription->subscriptionEvents);
    }

    public function testAcceptsOptionalFields(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
            description: 'Streaming service',
            link: 'https://netflix.com',
            logo: 'netflix.png',
        );

        self::assertSame('Streaming service', $subscription->description);
        self::assertSame('https://netflix.com', $subscription->link);
        self::assertSame('netflix.png', $subscription->logo);
    }

    public function testDefaultsOptionalFieldsToEmpty(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Spotify',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1000,
        );

        self::assertSame('', $subscription->description);
        self::assertSame('', $subscription->link);
        self::assertSame('', $subscription->logo);
    }

    public function testUpdateCreatesOnlyUpdateEventWhenOnlyGeneralFieldsChange(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $newCategory = new Category(name: 'Streaming');
        $subscription->update(
            category: $newCategory,
            name: 'Netflix Premium',
            lastPaidDate: new \DateTimeImmutable('2024-02-01'),
            description: 'Premium plan',
            link: 'https://netflix.com',
            logo: 'netflix.png',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        self::assertCount(1, $subscription->subscriptionEvents);
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        self::assertSame(SubscriptionEventType::Update, $event->type);
        self::assertArrayHasKey('category', $event->context);
        self::assertArrayHasKey('name', $event->context);
        self::assertArrayNotHasKey('cost', $event->context);
    }

    public function testUpdateCreatesOnlyCostChangeEventWhenOnlyCostFieldsChange(): void
    {
        $lastPaidDate = new \DateTimeImmutable('2024-01-01');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: $lastPaidDate,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: $lastPaidDate,
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Year,
            paymentPeriodCount: 1,
            cost: 15000,
        );

        self::assertCount(1, $subscription->subscriptionEvents);
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        self::assertSame(SubscriptionEventType::CostChange, $event->type);
        self::assertArrayHasKey('paymentPeriod', $event->context);
        self::assertArrayHasKey('cost', $event->context);
    }

    public function testUpdateCreatesBothEventsWhenBothTypesOfFieldsChange(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix Premium',
            lastPaidDate: new \DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Year,
            paymentPeriodCount: 1,
            cost: 15000,
        );

        self::assertCount(2, $subscription->subscriptionEvents);

        /** @var SubscriptionEvent $updateEvent */
        $updateEvent = $subscription->subscriptionEvents[0];
        /** @var SubscriptionEvent $costChangeEvent */
        $costChangeEvent = $subscription->subscriptionEvents[1];

        self::assertSame(SubscriptionEventType::Update, $updateEvent->type);
        self::assertSame(SubscriptionEventType::CostChange, $costChangeEvent->type);
    }

    public function testUpdateCreatesNoEventsWhenNoFieldsChange(): void
    {
        $lastPaidDate = new \DateTimeImmutable('2024-01-01');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: $lastPaidDate,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: $lastPaidDate,
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        self::assertCount(0, $subscription->subscriptionEvents);
    }

    public function testRecordPaymentUpdatesLastPaidDate(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $newPaidDate = new \DateTimeImmutable('2024-02-01');
        $subscription->recordPayment(
            paidDate: $newPaidDate,
            paymentType: PaymentType::Verified,
        );

        self::assertSame($newPaidDate, $subscription->lastPaidDate);
    }

    public function testRecordPaymentAddsPaymentToCollection(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
        );

        self::assertCount(1, $subscription->payments);
        /** @var Payment $payment */
        $payment = $subscription->payments->first();
        self::assertSame(PaymentType::Verified, $payment->type);
        self::assertSame(1500, $payment->amount);
    }

    public function testRecordPaymentUsesSubscriptionCostByDefault(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
        );

        /** @var Payment $payment */
        $payment = $subscription->payments->first();
        self::assertSame(1500, $payment->amount);
    }

    public function testRecordPaymentAcceptsCustomAmount(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
            amount: 2000,
        );

        self::assertCount(1, $subscription->payments);
        /** @var Payment $payment */
        $payment = $subscription->payments->first();
        self::assertSame(2000, $payment->amount);
    }

    public function testArchiveSetsArchivedToTrue(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->archive();

        self::assertTrue($subscription->archived);
    }

    public function testArchiveCreatesArchiveEvent(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->archive();

        self::assertCount(1, $subscription->subscriptionEvents);
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        self::assertSame(SubscriptionEventType::Archive, $event->type);
        self::assertSame([], $event->context);
    }

    public function testUnarchiveSetsArchivedToFalse(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->archive();
        $subscription->unarchive();

        self::assertFalse($subscription->archived);
    }

    public function testUnarchiveCreatesUnarchiveEvent(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );

        $subscription->archive();
        $subscription->unarchive();

        self::assertCount(2, $subscription->subscriptionEvents);
        /** @var SubscriptionEvent $archiveEvent */
        $archiveEvent = $subscription->subscriptionEvents[0];
        /** @var SubscriptionEvent $unarchiveEvent */
        $unarchiveEvent = $subscription->subscriptionEvents[1];

        self::assertSame(SubscriptionEventType::Archive, $archiveEvent->type);
        self::assertSame(SubscriptionEventType::Unarchive, $unarchiveEvent->type);
        self::assertSame([], $unarchiveEvent->context);
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Subscription(
            category: $this->category,
            name: '',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );
    }

    public function testRejectsWhitespaceName(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Subscription(
            category: $this->category,
            name: '   ',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
        );
    }

    public function testRejectsZeroCost(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 0,
        );
    }

    public function testRejectsNegativeCost(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: -100,
        );
    }

    public function testRejectsZeroPeriodCount(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 0,
            cost: 1500,
        );
    }

    public function testRejectsNegativePeriodCount(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Subscription(
            category: $this->category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: -1,
            cost: 1500,
        );
    }
}
