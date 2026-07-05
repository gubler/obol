<?php

// ABOUTME: Unit tests for GenerateDuePaymentsHandler - records a Generated payment for each due subscription.
// ABOUTME: A subscription is due when nextRenewal <= today; generating advances the anchor by one interval.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Payment;

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Message\Command\Payment\GenerateDuePaymentsCommand;
use App\Message\Command\Payment\GenerateDuePaymentsHandler;
use App\Repository\SubscriptionRepository;
use App\Tests\Support\InstantAssertions;
use App\ValueObject\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class GenerateDuePaymentsHandlerTest extends TestCase
{
    use InstantAssertions;

    private static function makeDuePaymentSubscription(PaymentPeriod $period, int $count, \DateTimeImmutable $nextRenewal): Subscription
    {
        return new Subscription(
            owner: new User(email: 'owner@example.com'),
            category: new Category(owner: new User(email: 'owner@example.com'), name: 'Test Category'),
            name: 'Test',
            nextRenewal: $nextRenewal,
            paymentPeriod: $period,
            paymentPeriodCount: $count,
            cost: new Money(1599, Currency::USD),
        );
    }

    /**
     * The handler trusts the finder: whichever subscriptions it returns are due (the timezone-aware
     * "is it due" filter lives in findAllPendingPaymentGeneration, covered by SubscriptionRepositoryTest).
     * So these tests feed the due set directly and assert the handler records a payment for each.
     *
     * @param list<Subscription> $subscriptions
     */
    private function runGenerateDuePayments(array $subscriptions): void
    {
        $clock = new MockClock(new \DateTimeImmutable('2026-08-01 04:00:00', new \DateTimeZone('UTC')));

        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->expects(self::once())->method('findAllPendingPaymentGeneration')
            ->with($clock->now())
            ->willReturn($subscriptions)
        ;

        (new GenerateDuePaymentsHandler($repository, $clock))(new GenerateDuePaymentsCommand());
    }

    public function testGeneratesAPaymentDatedToTheRenewalWhenItHasPassed(): void
    {
        $subscription = self::makeDuePaymentSubscription(PaymentPeriod::Month, 1, new \DateTimeImmutable('2020-01-01'));

        $this->runGenerateDuePayments([$subscription]);

        self::assertCount(1, $subscription->payments);
        /** @var Payment $payment */
        $payment = $subscription->payments->first();
        self::assertSame(PaymentType::Generated, $payment->type);
        self::assertSame(1599, $payment->amount->minorAmount);
        self::assertSameInstant(new \DateTimeImmutable('2020-01-01'), $payment->paidDate);
        self::assertSameInstant(new \DateTimeImmutable('2020-02-01'), $subscription->nextRenewal);
    }

    #[DataProvider('provideAdvancesTheRenewalAnchorByTheConfiguredIntervalCases')]
    public function testAdvancesTheRenewalAnchorByTheConfiguredInterval(PaymentPeriod $period, int $count, string $expected): void
    {
        $subscription = self::makeDuePaymentSubscription($period, $count, new \DateTimeImmutable('2020-01-01'));

        $this->runGenerateDuePayments([$subscription]);

        self::assertSameInstant(new \DateTimeImmutable($expected), $subscription->nextRenewal);
    }

    /**
     * @return iterable<string, array{PaymentPeriod, int, string}>
     */
    public static function provideAdvancesTheRenewalAnchorByTheConfiguredIntervalCases(): iterable
    {
        yield 'weekly' => [PaymentPeriod::Week, 1, '2020-01-08'];
        yield 'monthly' => [PaymentPeriod::Month, 1, '2020-02-01'];
        yield 'yearly' => [PaymentPeriod::Year, 1, '2021-01-01'];
        yield 'bi-weekly' => [PaymentPeriod::Week, 2, '2020-01-15'];
    }
}
