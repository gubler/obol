<?php

// ABOUTME: Unit tests for RecordObligationSnapshotHandler - the event-driven obligation recorder.
// ABOUTME: Sums active subscriptions' monthlyCost by currency and records a row only when it changes.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Event;

use App\Entity\Category;
use App\Entity\ObligationSnapshot;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Event\RecordObligationSnapshotHandler;
use App\Message\Event\SubscriptionsChanged;
use App\Repository\ObligationSnapshotRepository;
use App\Repository\SubscriptionRepository;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RecordObligationSnapshotHandlerTest extends TestCase
{
    private static function makeSubscriptionCosting(Money $cost, PaymentPeriod $period, int $count): Subscription
    {
        return new Subscription(
            owner: new User(email: 'owner@example.com'),
            category: new Category(owner: new User(email: 'owner@example.com'), name: 'Test Category'),
            name: 'Test',
            nextRenewal: new \DateTimeImmutable('2020-01-01'),
            paymentPeriod: $period,
            paymentPeriodCount: $count,
            cost: $cost,
        );
    }

    public function testRecordsASnapshotSummingMonthlyNormalizedCostByCurrencyWhenNoneExistsYet(): void
    {
        $subscriptions = [
            self::makeSubscriptionCosting(new Money(4000, Currency::USD), PaymentPeriod::Month, 1),   // 4000 USD/month
            self::makeSubscriptionCosting(new Money(12000, Currency::USD), PaymentPeriod::Year, 1),    // 1000 USD/month
            self::makeSubscriptionCosting(new Money(3000, Currency::EUR), PaymentPeriod::Month, 1),    // 3000 EUR/month
        ];

        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())->method('findBy')->with(['archived' => false])->willReturn($subscriptions);
        $snapshotRepository = self::createStub(ObligationSnapshotRepository::class);
        $snapshotRepository->method('findLatest')->willReturn(null);

        $captured = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->willReturnCallback(function (object $entity) use (&$captured): void {
                $captured = $entity;
            })
        ;
        // The event bus owns the transaction (doctrine_transaction middleware); the handler never flushes.
        $entityManager->expects(self::never())->method('flush');

        $handler = new RecordObligationSnapshotHandler($subscriptionRepository, $snapshotRepository, $entityManager, self::createStub(LoggerInterface::class));
        $handler(new SubscriptionsChanged());

        self::assertInstanceOf(ObligationSnapshot::class, $captured);
        // toEqual on an array is order-insensitive (loose ==); the by-currency map's key
        // order is not guaranteed, so canonicalize before comparing.
        self::assertEqualsCanonicalizing(['USD' => 5000, 'EUR' => 3000], $captured->obligationsByCurrency);
    }

    public function testRecordsANewSnapshotWhenTheObligationDiffersFromTheLatest(): void
    {
        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())->method('findBy')->with(['archived' => false])
            ->willReturn([self::makeSubscriptionCosting(new Money(4000, Currency::USD), PaymentPeriod::Month, 1)])
        ;
        $snapshotRepository = self::createStub(ObligationSnapshotRepository::class);
        $snapshotRepository->method('findLatest')->willReturn(new ObligationSnapshot(['USD' => 3000]));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');

        $handler = new RecordObligationSnapshotHandler($subscriptionRepository, $snapshotRepository, $entityManager, self::createStub(LoggerInterface::class));
        $handler(new SubscriptionsChanged());
    }

    public function testRecordsNothingWhenTheObligationEqualsTheLatestSnapshot(): void
    {
        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())->method('findBy')->with(['archived' => false])->willReturn([
            self::makeSubscriptionCosting(new Money(4000, Currency::USD), PaymentPeriod::Month, 1),
            self::makeSubscriptionCosting(new Money(3000, Currency::EUR), PaymentPeriod::Month, 1),
        ]);
        $snapshotRepository = self::createStub(ObligationSnapshotRepository::class);
        // Same totals, different key order - equality must be order-insensitive.
        $snapshotRepository->method('findLatest')->willReturn(new ObligationSnapshot(['EUR' => 3000, 'USD' => 4000]));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $handler = new RecordObligationSnapshotHandler($subscriptionRepository, $snapshotRepository, $entityManager, self::createStub(LoggerInterface::class));
        $handler(new SubscriptionsChanged());
    }

    public function testLogsAndSwallowsAFailureSoItNeverBreaksTheSubscriptionEditThatTriggeredIt(): void
    {
        $subscriptionRepository = self::createStub(SubscriptionRepository::class);
        $subscriptionRepository->method('findBy')->willThrowException(new \RuntimeException('db is down'));
        $snapshotRepository = self::createStub(ObligationSnapshotRepository::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $handler = new RecordObligationSnapshotHandler($subscriptionRepository, $snapshotRepository, $entityManager, $logger);
        $handler(new SubscriptionsChanged()); // must not throw
    }
}
