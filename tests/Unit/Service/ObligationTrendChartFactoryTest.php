<?php

// ABOUTME: Unit tests for ObligationTrendChartFactory - turning an ObligationSeries into a Chart.js line.
// ABOUTME: Asserts labels, per-bucket data, the formatted display amounts, and the currency formatting payload.

declare(strict_types=1);

use App\Enum\Currency;
use App\Enum\ObligationTrendPeriod;
use App\Message\Query\Report\ObligationPoint;
use App\Message\Query\Report\ObligationSeries;
use App\Service\ObligationTrendChartFactory;
use App\ValueObject\Money;
use Symfony\UX\Chartjs\Builder\ChartBuilder;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * @param list<array{string, int}> $points
 */
function trendSeries(array $points): ObligationSeries
{
    return new ObligationSeries(
        points: array_map(static fn (array $p): ObligationPoint => new ObligationPoint($p[0], new Money($p[1], Currency::USD)), $points),
        period: ObligationTrendPeriod::Month,
        asOf: new DateTimeImmutable('2026-06-13'),
        isApproximate: false,
    );
}

test('builds a line over the buckets with formatted display amounts and currency formatting', function (): void {
    $factory = new ObligationTrendChartFactory(new ChartBuilder());

    $chart = $factory->line(trendSeries([['Apr 2026', 0], ['May 2026', 5000], ['Jun 2026', 8000]]));

    $data = $chart->getData();

    expect($chart->getType())->toBe(Chart::TYPE_LINE)
        ->and($data['labels'])->toBe(['Apr 2026', 'May 2026', 'Jun 2026'])
        ->and($data['datasets'][0]['data'])->toBe([0, 5000, 8000])
        ->and($data['datasets'][0]['displayAmounts'])->toBe(['$0.00', '$50.00', '$80.00'])
        ->and($data['datasets'][0]['currencySymbol'])->toBe('$')
        ->and($data['datasets'][0]['fractionDigits'])->toBe(2)
    ;
});
