<?php

// ABOUTME: Unit tests for GenerateDuePaymentsHandler - records a Generated payment for each due subscription.
// ABOUTME: A subscription is due when nextRenewal <= today; generating advances the anchor by one interval.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Payment;

use App\Entity\Category;
use App\Entity\Payment;
use App\Entity\Subscription;
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

final class GenerateDuePaymentsHandlerTest extends TestCase
{
    use InstantAssertions;

    private static function makeDuePaymentSubscription(PaymentPeriod $period, int $count, \DateTimeImmutable $nextRenewal): Subscription
    {
        return new Subscription(
            category: new Category(name: 'Test Category'),
            name: 'Test',
            nextRenewal: $nextRenewal,
            paymentPeriod: $period,
            paymentPeriodCount: $count,
            cost: new Money(1599, Currency::USD),
        );
    }

    /**
     * @param list<Subscription> $subscriptions
     */
    private function runGenerateDuePayments(array $subscriptions): void
    {
        $repository = $this->createMock(SubscriptionRepository::class);
        $repository->method('findBy')->with(['archived' => false])->willReturn($subscriptions);

        (new GenerateDuePaymentsHandler($repository))(new GenerateDuePaymentsCommand());
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

    public function testSkipsASubscriptionWhoseRenewalIsInTheFuture(): void
    {
        $subscription = self::makeDuePaymentSubscription(PaymentPeriod::Month, 1, new \DateTimeImmutable('+10 days'));

        $this->runGenerateDuePayments([$subscription]);

        self::assertCount(0, $subscription->payments);
    }

    public function testSkipsASubscriptionSetToManualPaymentGenerationEvenWhenItsRenewalHasPassed(): void
    {
        $subscription = self::makeDuePaymentSubscription(PaymentPeriod::Month, 1, new \DateTimeImmutable('2020-01-01'));
        $subscription->switchToManualPayments();

        $this->runGenerateDuePayments([$subscription]);

        self::assertCount(0, $subscription->payments);
        self::assertSameInstant(new \DateTimeImmutable('2020-01-01'), $subscription->nextRenewal);
    }

    #[DataProvider('provideAdvancesTheRenewalAnchorByTheConfiguredIntervalCases')]
    public function testAdvancesTheRenewalAnchorByTheConfiguredInterval(PaymentPeriod $period, int $count, string $expected): void
    {
        $subscription = self::makeDuePaymentSubscription($period, $count, new \DateTimeImmutable('2020-01-01'));

        $this->runGenerateDuePayments([$subscription]);

        self::assertSameInstant(new \DateTimeImmutable($expected), $subscription->nextRenewal);
    }

    public static function provideAdvancesTheRenewalAnchorByTheConfiguredIntervalCases(): iterable
    {
        yield 'weekly' => [PaymentPeriod::Week, 1, '2020-01-08'];
        yield 'monthly' => [PaymentPeriod::Month, 1, '2020-02-01'];
        yield 'yearly' => [PaymentPeriod::Year, 1, '2021-01-01'];
        yield 'bi-weekly' => [PaymentPeriod::Week, 2, '2020-01-15'];
    }
}
