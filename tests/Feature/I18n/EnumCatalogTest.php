<?php

// ABOUTME: Asserts every user-facing enum label() key resolves through the translator (ADR-0012).
// ABOUTME: Guards completeness - a case with no `enum.*` catalog entry would render its raw key.

declare(strict_types=1);

namespace App\Tests\Feature\I18n;

use App\Enum\CategoryIcon;
use App\Enum\ObligationTrendPeriod;
use App\Enum\PaymentPeriod;
use App\Enum\PaymentType;
use App\Enum\SubscriptionEventType;
use App\Enum\SubscriptionSort;
use App\Enum\TileColor;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EnumCatalogTest extends KernelTestCase
{
    #[DataProvider('provideEveryUserFacingEnumLabelResolvesCases')]
    public function testEveryUserFacingEnumLabelResolves(string $key): void
    {
        self::bootKernel();

        $translator = self::getContainer()->get(TranslatorInterface::class);
        $translated = $translator->trans($key, locale: 'en');

        self::assertNotSame($key, $translated, $key . ' has no `en` catalog entry');
        self::assertNotSame('', $translated);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEveryUserFacingEnumLabelResolvesCases(): iterable
    {
        foreach (TileColor::cases() as $color) {
            yield 'tile color: ' . $color->value => [$color->label()];
        }

        foreach (CategoryIcon::cases() as $icon) {
            yield 'category icon: ' . $icon->value => [$icon->label()];
        }

        foreach (ObligationTrendPeriod::cases() as $period) {
            yield 'trend period: ' . $period->value => [$period->label()];
        }

        foreach (SubscriptionSort::cases() as $sort) {
            yield 'subscription sort: ' . $sort->value => [$sort->label()];
        }

        foreach (PaymentPeriod::cases() as $period) {
            yield 'payment period: ' . $period->value => [$period->label()];
        }

        foreach (PaymentType::cases() as $type) {
            yield 'payment type: ' . $type->value => [$type->label()];
        }

        foreach (SubscriptionEventType::cases() as $eventType) {
            yield 'subscription event type: ' . $eventType->value => [$eventType->label()];
        }
    }

    #[DataProvider('provideLabelEnglishSpotCheckCases')]
    public function testLabelEnglishSpotCheck(string $key, string $expected): void
    {
        self::bootKernel();

        $translator = self::getContainer()->get(TranslatorInterface::class);

        self::assertSame($expected, $translator->trans($key, locale: 'en'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideLabelEnglishSpotCheckCases(): iterable
    {
        yield 'grey swatch' => ['enum.tile_color.grey', 'Gray'];
        yield 'book-open icon' => ['enum.category_icon.book_open', 'Book Open'];
        yield 'gamepad icon' => ['enum.category_icon.gamepad_2', 'Gamepad 2'];
        yield 'weekly trend' => ['enum.obligation_trend_period.week', 'Weekly'];
        yield 'monthly cost sort' => ['enum.subscription_sort.monthly_cost', 'Monthly cost'];
        yield 'yearly payment period' => ['enum.payment_period.year', 'Year'];
        yield 'generated payment type' => ['enum.payment_type.generated', 'generated'];
        yield 'cost change event' => ['enum.subscription_event_type.cost_change', 'costChange'];
    }
}
