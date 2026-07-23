<?php

// ABOUTME: Unit tests for ObligationTrendChartFactory - turning an ObligationSeries into a Chart.js line.
// ABOUTME: Asserts labels, per-bucket data, the formatted display amounts, and the currency formatting payload.

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\Currency;
use App\Enum\ObligationTrendPeriod;
use App\Message\Query\Report\ObligationPoint;
use App\Message\Query\Report\ObligationSeries;
use App\Service\Money\MoneyFormatter;
use App\Service\ObligationTrendChartFactory;
use App\ValueObject\CalendarDate;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Translation\Translator;
use Symfony\UX\Chartjs\Builder\ChartBuilder;
use Symfony\UX\Chartjs\Model\Chart;

final class ObligationTrendChartFactoryTest extends TestCase
{
    public function testBuildsALineOverTheBucketsWithFormattedDisplayAmountsAndCurrencyFormatting(): void
    {
        // An explicit-locale formatter: the display strings below are pinned to `en`, not the ambient default.
        $factory = new ObligationTrendChartFactory(new ChartBuilder(), self::translator(), new MoneyFormatter(new LocaleSwitcher('en', [])));

        $chart = $factory->line(self::trendSeries([['Apr 2026', 0], ['May 2026', 5000], ['Jun 2026', 8000]]));

        $data = $chart->getData();

        self::assertSame(Chart::TYPE_LINE, $chart->getType());
        self::assertSame(['Apr 2026', 'May 2026', 'Jun 2026'], $data['labels']);
        self::assertSame('Obligation', $data['datasets'][0]['label']);
        self::assertSame([0, 5000, 8000], $data['datasets'][0]['data']);
        self::assertSame(['$0.00', '$50.00', '$80.00'], $data['datasets'][0]['displayAmounts']);
        self::assertSame('$', $data['datasets'][0]['currencySymbol']);
        self::assertSame(2, $data['datasets'][0]['fractionDigits']);
    }

    /**
     * A translator backed by the report-chart catalog entry so the dataset label stays 'Obligation' in en.
     */
    private static function translator(): Translator
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new \Symfony\Component\Translation\Loader\ArrayLoader());
        $translator->addResource('array', ['report.chart.obligation' => 'Obligation'], 'en');

        return $translator;
    }

    /**
     * @param list<array{string, int}> $points
     */
    private static function trendSeries(array $points): ObligationSeries
    {
        return new ObligationSeries(
            points: array_map(static fn (array $p): ObligationPoint => new ObligationPoint($p[0], new Money($p[1], Currency::USD)), $points),
            period: ObligationTrendPeriod::Month,
            asOf: CalendarDate::fromString('2026-06-13'),
            isApproximate: false,
        );
    }
}
