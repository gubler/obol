<?php

// ABOUTME: Unit tests for FindPaymentSourceCompositionRunner - each source's share of the monthly obligation.
// ABOUTME: Groups active subscriptions by payment source, converts each share, sorts by size, with an unassigned bucket.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query\Report;

use App\Entity\PaymentSource;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\Message\Currency\Converter;
use App\Message\Currency\CurrencyTotaller;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\FindPaymentSourceCompositionQuery;
use App\Message\Query\Report\FindPaymentSourceCompositionRunner;
use App\Repository\ExchangeRateRepository;
use App\Repository\SubscriptionRepository;
use App\Service\DisplayCurrencyProvider;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FindPaymentSourceCompositionRunnerTest extends TestCase
{
    private static function subscription(?PaymentSource $source, int $costMinor, Currency $currency = Currency::USD): Subscription
    {
        return new Subscription(
            owner: new User(email: 'owner@example.com'),
            category: null,
            name: 'Test',
            nextRenewal: new \DateTimeImmutable('2026-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money($costMinor, $currency),
            paymentSource: $source,
        );
    }

    /**
     * @param list<Subscription>   $subscriptions
     * @param array<string, float> $rates
     */
    private function runComposition(array $subscriptions, array $rates = []): Composition
    {
        $repository = self::createMock(SubscriptionRepository::class);
        $repository->expects(self::once())->method('findActiveForOwner')->willReturn($subscriptions);

        $exchangeRateRepository = self::createStub(ExchangeRateRepository::class);
        $exchangeRateRepository->method('latestRate')
            ->willReturnCallback(static fn (Currency $currency): ?float => $rates[$currency->value] ?? null)
        ;
        $totaller = new CurrencyTotaller(new Converter($exchangeRateRepository), new DisplayCurrencyProvider('USD'));

        $runner = new FindPaymentSourceCompositionRunner($repository, $totaller, self::translator());

        return $runner(new FindPaymentSourceCompositionQuery(ownerUserId: new Ulid()));
    }

    private static function translator(): TranslatorInterface
    {
        $translator = self::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $key): string => ['subscription.group.unassigned' => 'Unassigned'][$key] ?? $key,
        );

        return $translator;
    }

    public function testOneSlicePerSourceSortedByShareDescendingWithTheOverallTotal(): void
    {
        $amex = new PaymentSource(name: 'Amex 1234', color: TileColor::Violet);
        $visa = new PaymentSource(name: 'Visa 5678');

        $composition = $this->runComposition([
            self::subscription($amex, 1000),
            self::subscription($amex, 500),
            self::subscription($visa, 4000),
        ]);

        self::assertCount(2, $composition->slices);
        self::assertSame('Visa 5678', $composition->slices[0]->label);   // 4000, largest first
        self::assertSame(4000, $composition->slices[0]->converted->minorAmount);
        self::assertSame($visa->id, $composition->slices[0]->id);
        self::assertSame('Amex 1234', $composition->slices[1]->label);   // 1500
        self::assertSame(TileColor::Violet, $composition->slices[1]->color);
        self::assertSame(5500, $composition->total->converted->minorAmount);
        self::assertNull($composition->title);
    }

    public function testCollectsUnassignedSubscriptionsIntoASingleSliceWithNoId(): void
    {
        $amex = new PaymentSource(name: 'Amex 1234');

        $composition = $this->runComposition([
            self::subscription($amex, 1000),
            self::subscription(null, 4000),
            self::subscription(null, 500),
        ]);

        self::assertCount(2, $composition->slices);
        // The 4500 unassigned share is the largest, so it sorts first.
        self::assertSame('Unassigned', $composition->slices[0]->label);
        self::assertSame(4500, $composition->slices[0]->converted->minorAmount);
        self::assertNull($composition->slices[0]->id);
        self::assertSame(TileColor::Charcoal, $composition->slices[0]->color);
        self::assertSame('Amex 1234', $composition->slices[1]->label);
    }

    public function testIsEmptyWithAZeroTotalWhenThereAreNoActiveSubscriptions(): void
    {
        $composition = $this->runComposition([]);

        self::assertSame([], $composition->slices);
        self::assertSame(0, $composition->total->converted->minorAmount);
    }
}
