<?php

// ABOUTME: Unit tests for CompositionChartFactory - turning a Composition into a Chart.js pie definition.
// ABOUTME: Asserts labels, per-slice data, display-currency strings, and the native breakdown tooltip payload.

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\Currency;
use App\Message\Currency\ConvertedTotal;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\CompositionSlice;
use App\Service\CompositionChartFactory;
use App\Tests\Support\PinsDefaultLocale;
use App\ValueObject\CalendarDate;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\UX\Chartjs\Builder\ChartBuilder;
use Symfony\UX\Chartjs\Model\Chart;

final class CompositionChartFactoryTest extends TestCase
{
    // The factory calls Money::format() with no locale, so the assertions below depend on the default.
    use PinsDefaultLocale;

    public function testBuildsAPieWithOneLabelledSlicePerCategoryAndDisplayCurrencyAmounts(): void
    {
        $factory = new CompositionChartFactory(new ChartBuilder());

        $chart = $factory->pie(self::makeComposition(
            new CompositionSlice('Software', self::usd(4000), [self::usd(4000)], false),
            new CompositionSlice('Streaming', self::usd(1500), [self::usd(1500)], false),
        ));

        $data = $chart->getData();

        self::assertSame(Chart::TYPE_PIE, $chart->getType());
        self::assertSame(['Software', 'Streaming'], $data['labels']);
        self::assertSame([4000, 1500], $data['datasets'][0]['data']);
        self::assertSame(['$40.00', '$15.00'], $data['datasets'][0]['displayAmounts']);
        self::assertCount(2, $data['datasets'][0]['backgroundColor']);
    }

    public function testCarriesTheNativeBreakdownOnlyForConvertedApproximateSlices(): void
    {
        $factory = new CompositionChartFactory(new ChartBuilder());

        $chart = $factory->pie(self::makeComposition(
            new CompositionSlice('Mixed', self::usd(15400), [self::usd(10000), new Money(5000, Currency::EUR)], true),
            new CompositionSlice('Plain', self::usd(2000), [self::usd(2000)], false),
        ));

        $native = $chart->getData()['datasets'][0]['nativeBreakdown'];

        self::assertSame(['$100.00', '€50.00'], $native[0]); // approximate: native lines for the tooltip
        self::assertSame([], $native[1]);                    // not approximate: nothing extra to disclose
    }

    private static function usd(int $minor): Money
    {
        return new Money($minor, Currency::USD);
    }

    private static function makeComposition(CompositionSlice ...$slices): Composition
    {
        return new Composition(
            slices: array_values($slices),
            total: new ConvertedTotal(self::usd(0), [], false),
            asOf: CalendarDate::fromString('2026-06-13'),
        );
    }
}
