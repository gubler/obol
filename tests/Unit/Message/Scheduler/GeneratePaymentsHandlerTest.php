<?php

// ABOUTME: Unit tests for GeneratePaymentsHandler verifying automatic payment generation logic.
// ABOUTME: Tests due date calculation for all period types, period count, and archived subscription skipping.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Scheduler;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\PaymentPeriod;
use App\Message\Scheduler\GeneratePaymentsHandler;
use App\Message\Scheduler\GeneratePaymentsMessage;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class GeneratePaymentsHandlerTest extends TestCase
{
    public function testGeneratesPaymentForMonthlySubscriptionPastDue(): void
    {
        $category = $this->createCategory();
        $subscription = new Subscription(
            category: $category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable('-35 days'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1599,
        );

        $initialCount = \count($subscription->payments);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findBy')
            ->with(['archived' => false])
            ->willReturn([$subscription])
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $handler = new GeneratePaymentsHandler($repository, $entityManager);
        $handler(new GeneratePaymentsMessage());

        self::assertGreaterThan($initialCount, \count($subscription->payments));
    }

    public function testSkipsMonthlySubscriptionNotYetDue(): void
    {
        $category = $this->createCategory();
        $subscription = new Subscription(
            category: $category,
            name: 'Netflix',
            lastPaidDate: new \DateTimeImmutable('-10 days'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1599,
        );

        $initialCount = \count($subscription->payments);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findBy')
            ->with(['archived' => false])
            ->willReturn([$subscription])
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $handler = new GeneratePaymentsHandler($repository, $entityManager);
        $handler(new GeneratePaymentsMessage());

        self::assertCount($initialCount, $subscription->payments);
    }

    public function testGeneratesPaymentForWeeklySubscriptionPastDue(): void
    {
        $category = $this->createCategory();
        $subscription = new Subscription(
            category: $category,
            name: 'Weekly Sub',
            lastPaidDate: new \DateTimeImmutable('-8 days'),
            paymentPeriod: PaymentPeriod::Week,
            paymentPeriodCount: 1,
            cost: 500,
        );

        $initialCount = \count($subscription->payments);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findBy')
            ->with(['archived' => false])
            ->willReturn([$subscription])
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $handler = new GeneratePaymentsHandler($repository, $entityManager);
        $handler(new GeneratePaymentsMessage());

        self::assertGreaterThan($initialCount, \count($subscription->payments));
    }

    public function testGeneratesPaymentForYearlySubscriptionPastDue(): void
    {
        $category = $this->createCategory();
        $subscription = new Subscription(
            category: $category,
            name: 'Annual Sub',
            lastPaidDate: new \DateTimeImmutable('-13 months'),
            paymentPeriod: PaymentPeriod::Year,
            paymentPeriodCount: 1,
            cost: 9999,
        );

        $initialCount = \count($subscription->payments);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findBy')
            ->with(['archived' => false])
            ->willReturn([$subscription])
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $handler = new GeneratePaymentsHandler($repository, $entityManager);
        $handler(new GeneratePaymentsMessage());

        self::assertGreaterThan($initialCount, \count($subscription->payments));
    }

    public function testRespectsPaymentPeriodCountMultiplier(): void
    {
        $category = $this->createCategory();
        // Bi-weekly: paymentPeriodCount = 2, paymentPeriod = Week
        // Due after 14 days, paid 15 days ago → should generate
        $subscription = new Subscription(
            category: $category,
            name: 'Bi-weekly Sub',
            lastPaidDate: new \DateTimeImmutable('-15 days'),
            paymentPeriod: PaymentPeriod::Week,
            paymentPeriodCount: 2,
            cost: 500,
        );

        $initialCount = \count($subscription->payments);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findBy')
            ->with(['archived' => false])
            ->willReturn([$subscription])
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $handler = new GeneratePaymentsHandler($repository, $entityManager);
        $handler(new GeneratePaymentsMessage());

        self::assertGreaterThan($initialCount, \count($subscription->payments));
    }

    public function testSkipsBiWeeklySubscriptionNotYetDue(): void
    {
        $category = $this->createCategory();
        // Bi-weekly: paymentPeriodCount = 2, paymentPeriod = Week
        // Due after 14 days, paid 10 days ago → should NOT generate
        $subscription = new Subscription(
            category: $category,
            name: 'Bi-weekly Sub',
            lastPaidDate: new \DateTimeImmutable('-10 days'),
            paymentPeriod: PaymentPeriod::Week,
            paymentPeriodCount: 2,
            cost: 500,
        );

        $initialCount = \count($subscription->payments);

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findBy')
            ->with(['archived' => false])
            ->willReturn([$subscription])
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $handler = new GeneratePaymentsHandler($repository, $entityManager);
        $handler(new GeneratePaymentsMessage());

        self::assertCount($initialCount, $subscription->payments);
    }

    public function testDoesNotGenerateWhenNoSubscriptionsExist(): void
    {
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findBy')
            ->with(['archived' => false])
            ->willReturn([])
        ;

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $handler = new GeneratePaymentsHandler($repository, $entityManager);
        $handler(new GeneratePaymentsMessage());
    }

    private function createCategory(): Category
    {
        return new Category(name: 'Test Category');
    }
}
