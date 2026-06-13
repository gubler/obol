<?php

// ABOUTME: Unit tests for CompositionChartFactory - turning a Composition into a Chart.js pie definition.
// ABOUTME: Asserts labels, per-slice data, display-currency strings, and the native breakdown tooltip payload.

declare(strict_types=1);

use App\Enum\Currency;
use App\Message\Currency\ConvertedTotal;
use App\Message\Query\Report\Composition;
use App\Message\Query\Report\CompositionSlice;
use App\Service\CompositionChartFactory;
use App\ValueObject\Money;
use Symfony\UX\Chartjs\Builder\ChartBuilder;
use Symfony\UX\Chartjs\Model\Chart;

function usd(int $minor): Money
{
    return new Money($minor, Currency::USD);
}

function makeComposition(CompositionSlice ...$slices): Composition
{
    return new Composition(
        slices: array_values($slices),
        total: new ConvertedTotal(usd(0), [], false),
        asOf: new DateTimeImmutable('2026-06-13'),
    );
}

test('builds a pie with one labelled slice per category and display-currency amounts', function (): void {
    $factory = new CompositionChartFactory(new ChartBuilder());

    $chart = $factory->pie(makeComposition(
        new CompositionSlice('Software', usd(4000), [usd(4000)], false),
        new CompositionSlice('Streaming', usd(1500), [usd(1500)], false),
    ));

    $data = $chart->getData();

    expect($chart->getType())->toBe(Chart::TYPE_PIE)
        ->and($data['labels'])->toBe(['Software', 'Streaming'])
        ->and($data['datasets'][0]['data'])->toBe([4000, 1500])
        ->and($data['datasets'][0]['displayAmounts'])->toBe(['$40.00', '$15.00'])
        ->and($data['datasets'][0]['backgroundColor'])->toHaveCount(2)
    ;
});

test('carries the native breakdown only for converted (approximate) slices', function (): void {
    $factory = new CompositionChartFactory(new ChartBuilder());

    $chart = $factory->pie(makeComposition(
        new CompositionSlice('Mixed', usd(15400), [usd(10000), new Money(5000, Currency::EUR)], true),
        new CompositionSlice('Plain', usd(2000), [usd(2000)], false),
    ));

    $native = $chart->getData()['datasets'][0]['nativeBreakdown'];

    expect($native[0])->toBe(['$100.00', '€50.00'])  // approximate: native lines for the tooltip
        ->and($native[1])->toBe([])                  // not approximate: nothing extra to disclose
    ;
});
