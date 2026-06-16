<?php

// ABOUTME: Unit tests for Subscription entity ensuring proper instantiation and state validation.
// ABOUTME: Tests verify creation, update logic, payment recording, archival, and business invariants.

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\SubscriptionEvent;
use App\Enum\Currency;
use App\Enum\PaymentGeneration;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Enum\SubscriptionEventType;
use App\Enum\TileColor;
use App\Tests\Support\InstantAssertions;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class SubscriptionTest extends TestCase
{
    use InstantAssertions;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = new Category(name: 'Entertainment');
    }

    public function testCreatesSubscriptionWithValidData(): void
    {
        $nextRenewal = new \DateTimeImmutable('2024-01-01');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        self::assertSame($this->category, $subscription->category);
        self::assertSame('Netflix', $subscription->name);
        self::assertSame($nextRenewal, $subscription->nextRenewal);
        self::assertSame(PaymentPeriod::Month, $subscription->paymentPeriod);
        self::assertSame(1, $subscription->paymentPeriodCount);
        self::assertSame(1500, $subscription->cost->minorAmount);
    }

    public function testCreatesSubscriptionWithoutACategory(): void
    {
        $subscription = new Subscription(
            category: null,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        self::assertNull($subscription->category);
    }

    public function testRemovesACategoryByUpdatingToNull(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $subscription->update(
            category: null,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
            color: $subscription->color,
        );

        self::assertNull($subscription->category);
        self::assertCount(1, $subscription->subscriptionEvents);
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        self::assertSame(SubscriptionEventType::Update, $event->type);
        self::assertArrayHasKey('category', $event->context);
    }

    public function testAddsACategoryByUpdatingFromNull(): void
    {
        $subscription = new Subscription(
            category: null,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
            color: $subscription->color,
        );

        self::assertSame($this->category, $subscription->category);
        self::assertCount(1, $subscription->subscriptionEvents);
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        self::assertArrayHasKey('category', $event->context);
    }

    public function testSetsCreatedAtToCurrentTime(): void
    {
        $before = new \DateTimeImmutable();
        $subscription = new Subscription(
            category: $this->category,
            name: 'Spotify',
            nextRenewal: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1000, Currency::USD),
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
            nextRenewal: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1000, Currency::USD),
        );

        self::assertFalse($subscription->archived);
    }

    public function testInitializesEmptyCollections(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Spotify',
            nextRenewal: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1000, Currency::USD),
        );

        self::assertCount(0, $subscription->payments);
        self::assertCount(0, $subscription->subscriptionEvents);
    }

    public function testAcceptsOptionalFields(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
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
            nextRenewal: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1000, Currency::USD),
        );

        self::assertSame('', $subscription->description);
        self::assertSame('', $subscription->link);
        self::assertSame('', $subscription->logo);
    }

    public function testCreatesOnlyUpdateEventWhenOnlyGeneralFieldsChange(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $newCategory = new Category(name: 'Streaming');
        $subscription->update(
            category: $newCategory,
            name: 'Netflix Premium',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            description: 'Premium plan',
            link: 'https://netflix.com',
            logo: 'netflix.png',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
            color: $subscription->color,
        );

        self::assertCount(1, $subscription->subscriptionEvents);
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        self::assertSame(SubscriptionEventType::Update, $event->type);
        self::assertArrayHasKey('category', $event->context);
        self::assertArrayHasKey('name', $event->context);
        self::assertArrayNotHasKey('cost', $event->context);
    }

    public function testCreatesOnlyCostChangeEventWhenOnlyCostFieldsChange(): void
    {
        $nextRenewal = new \DateTimeImmutable('2024-01-01');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Year,
            paymentPeriodCount: 1,
            cost: new Money(15000, Currency::USD),
            color: $subscription->color,
        );

        self::assertCount(1, $subscription->subscriptionEvents);
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        self::assertSame(SubscriptionEventType::CostChange, $event->type);
        self::assertArrayHasKey('paymentPeriod', $event->context);
        self::assertArrayHasKey('cost', $event->context);
    }

    public function testRecordsAPeriodCountChangeUnderThePaymentPeriodCountKey(): void
    {
        $nextRenewal = new \DateTimeImmutable('2024-01-01');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 3,
            cost: new Money(1500, Currency::USD),
            color: $subscription->color,
        );

        self::assertCount(1, $subscription->subscriptionEvents);
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        self::assertSame(SubscriptionEventType::CostChange, $event->type);
        self::assertArrayHasKey('paymentPeriodCount', $event->context);
        self::assertArrayNotHasKey('paymentPeriodCost', $event->context);
        self::assertSame(['old' => 1, 'new' => 3], $event->context['paymentPeriodCount']);
    }

    public function testCreatesBothEventsWhenBothTypesOfFieldsChange(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix Premium',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Year,
            paymentPeriodCount: 1,
            cost: new Money(15000, Currency::USD),
            color: $subscription->color,
        );

        self::assertCount(2, $subscription->subscriptionEvents);

        /** @var SubscriptionEvent $updateEvent */
        $updateEvent = $subscription->subscriptionEvents[0];
        /** @var SubscriptionEvent $costChangeEvent */
        $costChangeEvent = $subscription->subscriptionEvents[1];

        self::assertSame(SubscriptionEventType::Update, $updateEvent->type);
        self::assertSame(SubscriptionEventType::CostChange, $costChangeEvent->type);
    }

    public function testCreatesNoEventsWhenNoFieldsChange(): void
    {
        $nextRenewal = new \DateTimeImmutable('2024-01-01');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $nextRenewal,
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
            color: $subscription->color,
        );

        self::assertCount(0, $subscription->subscriptionEvents);
    }

    public function testAdvancesNextRenewalByOneIntervalFromTheAnchor(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        // Paying late (on the 6th) must not move the anchor off the fixed cadence.
        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-02-06'),
            paymentType: PaymentType::Verified,
        );

        self::assertSameInstant(new \DateTimeImmutable('2024-03-01'), $subscription->nextRenewal);
    }

    public function testRollingBackARemovedPaymentPullsTheRenewalAnchorBack(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
        );
        /** @var Payment $payment */
        $payment = $subscription->payments->first();
        $subscription->removePayment($payment);

        self::assertCount(0, $subscription->payments);
        self::assertSameInstant(new \DateTimeImmutable('2024-02-01'), $subscription->nextRenewal);
    }

    public function testAddsPaymentToCollection(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
        );

        self::assertCount(1, $subscription->payments);
        /** @var Payment $payment */
        $payment = $subscription->payments->first();
        self::assertSame(PaymentType::Verified, $payment->type);
        self::assertSame(1500, $payment->amount->minorAmount);
    }

    public function testUsesSubscriptionCostByDefault(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
        );

        /** @var Payment $payment */
        $payment = $subscription->payments->first();
        self::assertSame(1500, $payment->amount->minorAmount);
    }

    public function testAcceptsCustomAmount(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
            amount: 2000,
        );

        self::assertCount(1, $subscription->payments);
        /** @var Payment $payment */
        $payment = $subscription->payments->first();
        self::assertSame(2000, $payment->amount->minorAmount);
    }

    public function testDefaultsToAutomated(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        self::assertSame(PaymentGeneration::Automated, $subscription->paymentGeneration);
        self::assertTrue($subscription->generatesPaymentsAutomatically());
    }

    public function testSwitchingToManualSetsPaymentGenerationToManual(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $subscription->switchToManualPayments();

        self::assertSame(PaymentGeneration::Manual, $subscription->paymentGeneration);
        self::assertFalse($subscription->generatesPaymentsAutomatically());
    }

    public function testRecordingAPaymentUnderManualGenerationLeavesTheRenewalAnchorUntouched(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );
        $subscription->switchToManualPayments();

        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
        );

        self::assertCount(1, $subscription->payments);
        self::assertSameInstant(new \DateTimeImmutable('2024-02-01'), $subscription->nextRenewal);
    }

    public function testRemovingAPaymentUnderManualGenerationLeavesTheRenewalAnchorUntouched(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );
        // Record while automated so the anchor advances to 2024-03-01, then switch to manual.
        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
        );
        $subscription->switchToManualPayments();
        /** @var Payment $payment */
        $payment = $subscription->payments->first();
        $subscription->removePayment($payment);

        self::assertCount(0, $subscription->payments);
        self::assertSameInstant(new \DateTimeImmutable('2024-03-01'), $subscription->nextRenewal);
    }

    public function testRemovingTheLatestGeneratedPaymentSwitchesToManualAndRollsBackTheAnchor(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );
        // The scheduler records the due payment as Generated, which advances the anchor to 2024-03-01.
        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Generated,
        );
        /** @var Payment $payment */
        $payment = $subscription->payments->first();

        $subscription->removeLatestPayment($payment);

        self::assertCount(0, $subscription->payments);
        // Deleting a generated payment means "I did not pay this", so generation passes to the user.
        self::assertSame(PaymentGeneration::Manual, $subscription->paymentGeneration);
        self::assertSameInstant(new \DateTimeImmutable('2024-02-01'), $subscription->nextRenewal);
    }

    public function testRemovingTheLatestVerifiedPaymentRollsBackButLeavesGenerationAutomated(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );
        // A user-recorded current-period payment advances the anchor to 2024-03-01.
        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-01-15'),
            paymentType: PaymentType::Verified,
        );
        /** @var Payment $payment */
        $payment = $subscription->payments->first();

        $subscription->removeLatestPayment($payment);

        self::assertCount(0, $subscription->payments);
        // Deleting a verified payment is data correction, so generation stays automated.
        self::assertSame(PaymentGeneration::Automated, $subscription->paymentGeneration);
        self::assertSameInstant(new \DateTimeImmutable('2024-02-01'), $subscription->nextRenewal);
    }

    public function testBackfillingAHistoricalPaymentDoesNotAdvanceTheAnchor(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        // A payment for a prior period (well before the current period that ends on the anchor).
        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2023-12-15'),
            paymentType: PaymentType::Verified,
        );
        /** @var Payment $payment */
        $payment = $subscription->payments->first();

        self::assertFalse($payment->advancedRenewal);
        self::assertSameInstant(new \DateTimeImmutable('2024-02-01'), $subscription->nextRenewal);
    }

    public function testAPaymentOnThePeriodBoundaryDoesNotAdvanceTheAnchor(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        // The boundary (anchor minus one interval) belongs to the prior period: not strictly greater.
        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-01-01'),
            paymentType: PaymentType::Verified,
        );
        /** @var Payment $payment */
        $payment = $subscription->payments->first();

        self::assertFalse($payment->advancedRenewal);
        self::assertSameInstant(new \DateTimeImmutable('2024-02-01'), $subscription->nextRenewal);
    }

    public function testAnInPeriodPaymentAdvancesTheAnchorAndIsFlagged(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-01-15'),
            paymentType: PaymentType::Verified,
        );
        /** @var Payment $payment */
        $payment = $subscription->payments->first();

        self::assertTrue($payment->advancedRenewal);
        self::assertSameInstant(new \DateTimeImmutable('2024-03-01'), $subscription->nextRenewal);
    }

    public function testRemovingABackfilledPaymentDoesNotRollBackTheAnchor(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );
        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2023-12-15'),
            paymentType: PaymentType::Verified,
        );
        /** @var Payment $payment */
        $payment = $subscription->payments->first();

        $subscription->removePayment($payment);

        self::assertCount(0, $subscription->payments);
        self::assertSameInstant(new \DateTimeImmutable('2024-02-01'), $subscription->nextRenewal);
    }

    public function testProjectsTheRolledBackAnchorWhenRemovingAnAdvancingPayment(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );
        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-01-15'),
            paymentType: PaymentType::Generated,
        );
        /** @var Payment $payment */
        $payment = $subscription->payments->first();

        self::assertTrue($subscription->removalRollsBackAnchor($payment));
        self::assertSameInstant(new \DateTimeImmutable('2024-02-01'), $subscription->renewalAfterRemoving($payment));
        self::assertTrue($subscription->removalSwitchesToManual($payment));
    }

    public function testProjectsNoConsequenceWhenRemovingABackfilledVerifiedPayment(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );
        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2023-12-15'),
            paymentType: PaymentType::Verified,
        );
        /** @var Payment $payment */
        $payment = $subscription->payments->first();

        self::assertFalse($subscription->removalRollsBackAnchor($payment));
        self::assertSameInstant(new \DateTimeImmutable('2024-02-01'), $subscription->renewalAfterRemoving($payment));
        self::assertFalse($subscription->removalSwitchesToManual($payment));
    }

    public function testRemovingAPaymentThatIsNotTheLatestIsRejected(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );
        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-01-01'),
            paymentType: PaymentType::Verified,
        );
        $subscription->recordPayment(
            paidDate: new \DateTimeImmutable('2024-02-01'),
            paymentType: PaymentType::Verified,
        );
        /** @var Payment $older */
        $older = $subscription->payments->first();

        $this->expectException(\Assert\InvalidArgumentException::class);

        $subscription->removeLatestPayment($older);
    }

    public function testAutomatingSetsGenerationToAutomatedAndAnchorsTheFutureRenewal(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );
        $subscription->switchToManualPayments();

        $future = new \DateTimeImmutable('tomorrow');
        $subscription->automatePayments($future);

        self::assertSame(PaymentGeneration::Automated, $subscription->paymentGeneration);
        self::assertTrue($subscription->generatesPaymentsAutomatically());
        self::assertSameInstant($future, $subscription->nextRenewal);
    }

    public function testAutomatingWithANonFutureRenewalIsRejected(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-02-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );
        $subscription->switchToManualPayments();

        $this->expectException(\Assert\InvalidArgumentException::class);

        $subscription->automatePayments(new \DateTimeImmutable('2020-01-01'));
    }

    public function testSuggestedResumeRenewalStepsTheCadenceToTheFirstDateAfterToday(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2020-01-15'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $suggested = $subscription->suggestedResumeRenewal();

        // Lands strictly after today and stays on the original day-of-cadence (the 15th).
        self::assertGreaterThan(new \DateTimeImmutable('today'), $suggested);
        self::assertSame('15', $suggested->format('d'));
    }

    public function testSuggestedResumeRenewalKeepsARenewalThatIsAlreadyInTheFuture(): void
    {
        $future = new \DateTimeImmutable('+40 days');
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: $future,
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        self::assertSameInstant($future, $subscription->suggestedResumeRenewal());
    }

    public function testSetsArchivedToTrue(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $subscription->archive();

        self::assertTrue($subscription->archived);
    }

    public function testCreatesArchiveEvent(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
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
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
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
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
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
            nextRenewal: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );
    }

    public function testRejectsWhitespaceName(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Subscription(
            category: $this->category,
            name: '   ',
            nextRenewal: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );
    }

    public function testRejectsZeroCost(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(0, Currency::USD),
        );
    }

    public function testRejectsNegativeCost(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(-100, Currency::USD),
        );
    }

    public function testRejectsZeroPeriodCount(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 0,
            cost: new Money(1500, Currency::USD),
        );
    }

    public function testRejectsNegativePeriodCount(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: -1,
            cost: new Money(1500, Currency::USD),
        );
    }

    public function testUpdateRejectsEmptyName(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $this->expectException(\Assert\InvalidArgumentException::class);

        $subscription->update(
            category: $this->category,
            name: '',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
            color: $subscription->color,
        );
    }

    public function testUpdateRejectsWhitespaceName(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $this->expectException(\Assert\InvalidArgumentException::class);

        $subscription->update(
            category: $this->category,
            name: '   ',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
            color: $subscription->color,
        );
    }

    public function testUpdateRejectsZeroCost(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $this->expectException(\Assert\InvalidArgumentException::class);

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(0, Currency::USD),
            color: $subscription->color,
        );
    }

    public function testUpdateRejectsNegativeCost(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $this->expectException(\Assert\InvalidArgumentException::class);

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(-100, Currency::USD),
            color: $subscription->color,
        );
    }

    public function testUpdateRejectsZeroPeriodCount(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $this->expectException(\Assert\InvalidArgumentException::class);

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 0,
            cost: new Money(1500, Currency::USD),
            color: $subscription->color,
        );
    }

    public function testUpdateRejectsNegativePeriodCount(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $this->expectException(\Assert\InvalidArgumentException::class);

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: -1,
            cost: new Money(1500, Currency::USD),
            color: $subscription->color,
        );
    }

    public function testTrimsNameOnCreation(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: '  Netflix  ',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        self::assertSame('Netflix', $subscription->name);
    }

    public function testUpdateTrimsName(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        $subscription->update(
            category: $this->category,
            name: '  Netflix Premium  ',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
            color: $subscription->color,
        );

        self::assertSame('Netflix Premium', $subscription->name);
    }

    public function testAssignsARandomPaletteColorWhenNoneIsGiven(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
        );

        self::assertInstanceOf(TileColor::class, $subscription->color);
    }

    public function testAcceptsAnExplicitColor(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
            color: TileColor::Blue,
        );

        self::assertSame(TileColor::Blue, $subscription->color);
    }

    public function testRecordsAColorChangeAsAnUpdateEvent(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
            color: TileColor::Blue,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
            color: TileColor::Red,
        );

        self::assertSame(TileColor::Red, $subscription->color);
        self::assertCount(1, $subscription->subscriptionEvents);
        /** @var SubscriptionEvent $event */
        $event = $subscription->subscriptionEvents->first();
        self::assertSame(SubscriptionEventType::Update, $event->type);
        self::assertArrayHasKey('color', $event->context);
        self::assertSame(['old' => 'blue', 'new' => 'red'], $event->context['color']);
    }

    public function testRecordsNoEventWhenTheColorIsUnchanged(): void
    {
        $subscription = new Subscription(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
            color: TileColor::Blue,
        );

        $subscription->update(
            category: $this->category,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
            color: TileColor::Blue,
        );

        self::assertCount(0, $subscription->subscriptionEvents);
    }

    public function testReturnsTheCostItselfForAMonthlySubscription(): void
    {
        $subscription = $this->makeSubscription(PaymentPeriod::Month, 1, 1500);

        self::assertSame(1500, $subscription->monthlyCost()->minorAmount);
    }

    public function testDividesByThePeriodCountForAMultiMonthSubscription(): void
    {
        // 3000 every 3 months is 1000 per month.
        $subscription = $this->makeSubscription(PaymentPeriod::Month, 3, 3000);

        self::assertSame(1000, $subscription->monthlyCost()->minorAmount);
    }

    public function testDividesAYearlyCostAcrossTwelveMonths(): void
    {
        // 12000 per year is 1000 per month.
        $subscription = $this->makeSubscription(PaymentPeriod::Year, 1, 12000);

        self::assertSame(1000, $subscription->monthlyCost()->minorAmount);
    }

    public function testNormalizesAMultiYearSubscription(): void
    {
        // 4800 every 2 years is 200 per month.
        $subscription = $this->makeSubscription(PaymentPeriod::Year, 2, 4800);

        self::assertSame(200, $subscription->monthlyCost()->minorAmount);
    }

    public function testNormalizesAWeeklySubscriptionUsing52WeeksPerYear(): void
    {
        // 1000 per week is 1000 * 52 / 12 = 4333.33 -> 4333 cents per month.
        $subscription = $this->makeSubscription(PaymentPeriod::Week, 1, 1000);

        self::assertSame(4333, $subscription->monthlyCost()->minorAmount);
    }

    public function testRoundsToTheNearestWholeCent(): void
    {
        // 1000 per year is 83.33 -> 83 cents per month.
        $subscription = $this->makeSubscription(PaymentPeriod::Year, 1, 1000);

        self::assertSame(83, $subscription->monthlyCost()->minorAmount);
    }

    public function testRampsByOneMonthlyCostPerCalendarMonthTowardTheRenewal(): void
    {
        // 1200 every 6 months -> 200/mo, due 2024-04-28. Funded by the 1st of March (a month ahead);
        // by 2024-01-15 four monthly allocations (Oct..Jan) have been made -> 800.
        $subscription = $this->makeSubscription(PaymentPeriod::Month, 6, 1200, '2024-04-28');

        self::assertSame(800, $subscription->savingsTarget(new \DateTimeImmutable('2024-01-15'))->minorAmount);
    }

    public function testHoldsTheFundedCostAndTheNextCycleTogetherInTheUnpaidDueMonth(): void
    {
        // In April the 1200 for the 2024-04-28 bill is funded and held (not yet paid), while 200
        // toward the October renewal has already begun -> 1400.
        $subscription = $this->makeSubscription(PaymentPeriod::Month, 6, 1200, '2024-04-28');

        self::assertSame(1400, $subscription->savingsTarget(new \DateTimeImmutable('2024-04-15'))->minorAmount);
    }

    public function testDropsToTheNextCycleOnceTheRenewalIsRecordedPaid(): void
    {
        // Recording the April payment advances nextRenewal to October; the held 1200 is released,
        // leaving the first 200 of the October cycle.
        $subscription = $this->makeSubscription(PaymentPeriod::Month, 6, 1200, '2024-10-28');

        self::assertSame(200, $subscription->savingsTarget(new \DateTimeImmutable('2024-04-28'))->minorAmount);
    }

    public function testStacksThisMonthAndNextForAMonthlyBillInItsUnpaidDueMonth(): void
    {
        // 100 monthly due the 15th: on the 8th the bill due on the 15th is held (100) and next
        // month's allocation has begun (100) -> 200.
        $subscription = $this->makeSubscription(PaymentPeriod::Month, 1, 100, '2024-04-15');

        self::assertSame(200, $subscription->savingsTarget(new \DateTimeImmutable('2024-04-08'))->minorAmount);
    }

    public function testIsOnePaymentForAMonthlyBillTheMonthBeforeItIsDue(): void
    {
        // 1500 monthly due 2024-02-01: in January only the funded February bill is held; saving for
        // the March bill has not begun -> 1500.
        $subscription = $this->makeSubscription(PaymentPeriod::Month, 1, 1500, '2024-02-01');

        self::assertSame(1500, $subscription->savingsTarget(new \DateTimeImmutable('2024-01-15'))->minorAmount);
    }

    public function testTreatsAWeeklyBillAsOnePaymentInHand(): void
    {
        // By-month proration cannot split a weekly cadence; until by-week proration lands a weekly
        // bill is just one payment held.
        $subscription = $this->makeSubscription(PaymentPeriod::Week, 1, 1000, '2024-01-08');

        self::assertSame(1000, $subscription->savingsTarget(new \DateTimeImmutable('2024-01-05'))->minorAmount);
    }

    public function testIsZeroBeforeTheFirstCycleHasBegun(): void
    {
        // A future renewal whose funding window has not opened yet has nothing to set aside.
        $subscription = $this->makeSubscription(PaymentPeriod::Year, 1, 12000, '2025-01-01');

        self::assertSame(0, $subscription->savingsTarget(new \DateTimeImmutable('2023-12-01'))->minorAmount);
    }

    public function testHoldsAnOverdueRenewalInFullOnTopOfSavingForTheNext(): void
    {
        // 12000 yearly due 2024-01-01, still unpaid by March: the full 12000 is held while three
        // monthly allocations toward the 2025 renewal have been made -> 15000.
        $subscription = $this->makeSubscription(PaymentPeriod::Year, 1, 12000, '2024-01-01');

        self::assertSame(15000, $subscription->savingsTarget(new \DateTimeImmutable('2024-03-01'))->minorAmount);
    }

    public function testAllowsChangingTheCurrencyWhileThereAreNoPayments(): void
    {
        $subscription = $this->makeSubscription(PaymentPeriod::Month, 1, 1500, '2024-02-01');

        $this->updateCost($subscription, new Money(1500, Currency::EUR));

        self::assertSame(Currency::EUR, $subscription->cost->currency);
    }

    public function testRejectsChangingTheCurrencyOnceAPaymentHasBeenRecorded(): void
    {
        $subscription = $this->makeSubscription(PaymentPeriod::Month, 1, 1500, '2024-02-01');
        $subscription->recordPayment(paidDate: new \DateTimeImmutable('2024-02-01'), paymentType: PaymentType::Verified);

        $this->expectException(\Assert\InvalidArgumentException::class);

        $this->updateCost($subscription, new Money(1500, Currency::EUR));
    }

    public function testAllowsASameCurrencyCostChangeAfterAPaymentExists(): void
    {
        $subscription = $this->makeSubscription(PaymentPeriod::Month, 1, 1500, '2024-02-01');
        $subscription->recordPayment(paidDate: new \DateTimeImmutable('2024-02-01'), paymentType: PaymentType::Verified);

        $this->updateCost($subscription, new Money(1999, Currency::USD));

        self::assertSame(1999, $subscription->cost->minorAmount);
        self::assertSame(Currency::USD, $subscription->cost->currency);
    }

    private function makeSubscription(PaymentPeriod $period, int $count, int $cost, string $nextRenewal = '2024-01-01'): Subscription
    {
        return new Subscription(
            category: new Category(name: 'Entertainment'),
            name: 'Example',
            nextRenewal: new \DateTimeImmutable($nextRenewal),
            paymentPeriod: $period,
            paymentPeriodCount: $count,
            cost: new Money($cost, Currency::USD),
        );
    }

    private function updateCost(Subscription $subscription, Money $cost): void
    {
        $subscription->update(
            category: $subscription->category,
            name: $subscription->name,
            nextRenewal: $subscription->nextRenewal,
            description: '',
            link: '',
            logo: '',
            paymentPeriod: $subscription->paymentPeriod,
            paymentPeriodCount: $subscription->paymentPeriodCount,
            cost: $cost,
            color: $subscription->color,
        );
    }
}
