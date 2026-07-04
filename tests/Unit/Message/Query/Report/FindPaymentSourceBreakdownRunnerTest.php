<?php

// ABOUTME: Unit tests for FindPaymentSourceBreakdownRunner - one source's subscriptions as a composition pie.
// ABOUTME: Resolves the source (null when missing), converts each active subscription's monthly share.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query\Report;

use App\Entity\PaymentSource;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\FindPaymentSourceBreakdownQuery;
use App\Message\Query\Report\FindPaymentSourceBreakdownRunner;
use App\Repository\ExchangeRateRepository;
use App\Repository\PaymentSourceRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserRepository;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FindPaymentSourceBreakdownRunnerTest extends TestCase
{
    private static function subscription(?PaymentSource $source, string $name, int $costMinor): Subscription
    {
        return new Subscription(
            owner: new User(email: 'owner@example.com'),
            category: null,
            name: $name,
            nextRenewal: new \DateTimeImmutable('2026-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money($costMinor, Currency::USD),
            paymentSource: $source,
        );
    }

    /**
     * @param list<Subscription> $subscriptions
     */
    private function runBreakdown(?PaymentSource $source, array $subscriptions = []): ?Composition
    {
        $ownerUserId = new Ulid();
        $sourceId = $source?->id ?? new Ulid();

        $paymentSourceRepository = self::createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::once())->method('find')->with($sourceId)->willReturn($source);

        $subscriptionRepository = self::createStub(SubscriptionRepository::class);
        $subscriptionRepository->method('findActiveForOwnerByPaymentSource')
            ->willReturnMap([
                [$ownerUserId, $source, $subscriptions],
            ])
        ;

        $totaller = $this->totaller();

        $runner = new FindPaymentSourceBreakdownRunner($paymentSourceRepository, $subscriptionRepository, $totaller, self::userRepository(), self::translator());

        return $runner(new FindPaymentSourceBreakdownQuery(ownerUserId: $ownerUserId, paymentSourceId: $sourceId));
    }

    private function totaller(): CurrencyTotaller
    {
        $exchangeRateRepository = self::createStub(ExchangeRateRepository::class);
        $exchangeRateRepository->method('latestRate')->willReturn(null);

        return new CurrencyTotaller(new Converter($exchangeRateRepository));
    }

    private static function userRepository(): UserRepository
    {
        $userRepository = self::createStub(UserRepository::class);
        $userRepository->method('getForId')->willReturn(new User(email: 'owner@example.com'));

        return $userRepository;
    }

    private static function translator(): TranslatorInterface
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $key): string => ['subscription.group.unassigned' => 'Unassigned'][$key] ?? $key,
        );

        return $translator;
    }

    public function testOneSlicePerSubscriptionSortedBySizeTitledWithTheSourceName(): void
    {
        $amex = new PaymentSource(name: 'Amex 1234');

        $composition = $this->runBreakdown($amex, [
            self::subscription($amex, 'Netflix', 1599),
            self::subscription($amex, 'Spotify', 1099),
        ]);

        self::assertInstanceOf(Composition::class, $composition);
        self::assertSame('Amex 1234', $composition->title);
        self::assertCount(2, $composition->slices);
        self::assertSame('Netflix', $composition->slices[0]->label);   // 1599, largest first
        self::assertSame(1599, $composition->slices[0]->converted->minorAmount);
        self::assertNull($composition->slices[0]->id);                  // leaf slice, no deeper drill-down
        self::assertSame(2698, $composition->total->converted->minorAmount);
    }

    public function testBuildsAnUnassignedBreakdownWhenSourceIdIsNull(): void
    {
        $subscriptions = [
            self::subscription(null, 'Orphan', 1599),
            self::subscription(null, 'Stray', 1099),
        ];

        $ownerUserId = new Ulid();

        // No source is resolved for the unassigned drill-down; it filters on a null payment source.
        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::never())->method('find');

        $subscriptionRepository = self::createStub(SubscriptionRepository::class);
        $subscriptionRepository->method('findActiveForOwnerByPaymentSource')
            ->willReturnMap([
                [$ownerUserId, null, $subscriptions],
            ])
        ;

        $runner = new FindPaymentSourceBreakdownRunner($paymentSourceRepository, $subscriptionRepository, $this->totaller(), self::userRepository(), self::translator());
        $composition = $runner(new FindPaymentSourceBreakdownQuery(ownerUserId: $ownerUserId, paymentSourceId: null));

        self::assertInstanceOf(Composition::class, $composition);
        self::assertSame('Unassigned', $composition->title);
        self::assertCount(2, $composition->slices);
        self::assertSame('Orphan', $composition->slices[0]->label);
        self::assertSame(2698, $composition->total->converted->minorAmount);
    }

    public function testReturnsNullWhenTheSourceDoesNotExist(): void
    {
        self::assertNull($this->runBreakdown(null));
    }

    public function testIsAnEmptyZeroTotalPieForASourceWithNoActiveSubscriptions(): void
    {
        $empty = new PaymentSource(name: 'Empty');

        $composition = $this->runBreakdown($empty, []);

        self::assertInstanceOf(Composition::class, $composition);
        self::assertSame([], $composition->slices);
        self::assertSame(0, $composition->total->converted->minorAmount);
    }
}
