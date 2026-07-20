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
use App\Repository\UserRepository;
use App\ValueObject\CalendarDate;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Ulid;

final class RecordObligationSnapshotHandlerTest extends TestCase
{
    private static function makeSubscriptionCosting(Money $cost, PaymentPeriod $period, int $count): Subscription
    {
        return new Subscription(
            owner: new User(email: 'owner@example.com'),
            category: new Category(owner: new User(email: 'owner@example.com'), name: 'Test Category'),
            name: 'Test',
            nextRenewal: CalendarDate::fromString('2020-01-01'),
            paymentPeriod: $period,
            paymentPeriodCount: $count,
            cost: $cost,
            now: new \DateTimeImmutable('2000-01-01', new \DateTimeZone('UTC')),
        );
    }

    private static function userRepositoryReturning(User $user): UserRepository
    {
        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('getForId')->willReturn($user);

        return $userRepository;
    }

    public function testRecordsASnapshotSummingMonthlyNormalizedCostByCurrencyWhenNoneExistsYet(): void
    {
        $subscriptions = [
            self::makeSubscriptionCosting(new Money(4000, Currency::USD), PaymentPeriod::Month, 1),   // 4000 USD/month
            self::makeSubscriptionCosting(new Money(12000, Currency::USD), PaymentPeriod::Year, 1),    // 1000 USD/month
            self::makeSubscriptionCosting(new Money(3000, Currency::EUR), PaymentPeriod::Month, 1),    // 3000 EUR/month
        ];

        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())->method('findActiveForOwner')->willReturn($subscriptions);
        $snapshotRepository = self::createStub(ObligationSnapshotRepository::class);
        $snapshotRepository->method('findLatestForOwner')->willReturn(null);

        $captured = null;
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->willReturnCallback(function (object $entity) use (&$captured): void {
                $captured = $entity;
            })
        ;
        // The event bus owns the transaction (doctrine_transaction middleware); the handler never flushes.
        $entityManager->expects(self::never())->method('flush');

        $handler = new RecordObligationSnapshotHandler($subscriptionRepository, $snapshotRepository, self::userRepositoryReturning(new User(email: 'owner@example.com')), $entityManager, self::createStub(LoggerInterface::class), new MockClock());
        $handler(new SubscriptionsChanged(new Ulid()));

        self::assertInstanceOf(ObligationSnapshot::class, $captured);
        // toEqual on an array is order-insensitive (loose ==); the by-currency map's key
        // order is not guaranteed, so canonicalize before comparing.
        self::assertEqualsCanonicalizing(['USD' => 5000, 'EUR' => 3000], $captured->obligationsByCurrency);
    }

    public function testRecordsANewSnapshotWhenTheObligationDiffersFromTheLatest(): void
    {
        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())->method('findActiveForOwner')
            ->willReturn([self::makeSubscriptionCosting(new Money(4000, Currency::USD), PaymentPeriod::Month, 1)])
        ;
        $snapshotRepository = self::createStub(ObligationSnapshotRepository::class);
        $snapshotRepository->method('findLatestForOwner')->willReturn(new ObligationSnapshot(new User(email: 'owner@example.com'), ['USD' => 3000], CalendarDate::fromString('2026-01-01')));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');

        $handler = new RecordObligationSnapshotHandler($subscriptionRepository, $snapshotRepository, self::userRepositoryReturning(new User(email: 'owner@example.com')), $entityManager, self::createStub(LoggerInterface::class), new MockClock());
        $handler(new SubscriptionsChanged(new Ulid()));
    }

    public function testRecordsNothingWhenTheObligationEqualsTheLatestSnapshot(): void
    {
        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())->method('findActiveForOwner')->willReturn([
            self::makeSubscriptionCosting(new Money(4000, Currency::USD), PaymentPeriod::Month, 1),
            self::makeSubscriptionCosting(new Money(3000, Currency::EUR), PaymentPeriod::Month, 1),
        ]);
        $snapshotRepository = self::createStub(ObligationSnapshotRepository::class);
        // Same totals, different key order - equality must be order-insensitive.
        $snapshotRepository->method('findLatestForOwner')->willReturn(new ObligationSnapshot(new User(email: 'owner@example.com'), ['EUR' => 3000, 'USD' => 4000], CalendarDate::fromString('2026-01-01')));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $handler = new RecordObligationSnapshotHandler($subscriptionRepository, $snapshotRepository, self::userRepositoryReturning(new User(email: 'owner@example.com')), $entityManager, self::createStub(LoggerInterface::class), new MockClock());
        $handler(new SubscriptionsChanged(new Ulid()));
    }

    public function testLogsAndSwallowsAFailureSoItNeverBreaksTheSubscriptionEditThatTriggeredIt(): void
    {
        $subscriptionRepository = self::createStub(SubscriptionRepository::class);
        $subscriptionRepository->method('findActiveForOwner')->willThrowException(new \RuntimeException('db is down'));
        $snapshotRepository = self::createStub(ObligationSnapshotRepository::class);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $handler = new RecordObligationSnapshotHandler($subscriptionRepository, $snapshotRepository, self::userRepositoryReturning(new User(email: 'owner@example.com')), $entityManager, $logger, new MockClock());
        $handler(new SubscriptionsChanged(new Ulid())); // must not throw
    }
}
