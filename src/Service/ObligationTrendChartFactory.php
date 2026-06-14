<?php

// ABOUTME: Turns an ObligationSeries into a Chart.js line definition for rendering with render_chart().
// ABOUTME: Carries formatted display amounts and the currency symbol/digits for the obligation-trend tooltip and axis.

declare(strict_types=1);

namespace App\Service;

use App\Message\Query\Report\ObligationPoint;
use App\Message\Query\Report\ObligationSeries;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final readonly class ObligationTrendChartFactory
{
    private const string LINE_COLOUR = '#6366f1';
    private const string FILL_COLOUR = 'rgba(99, 102, 241, 0.15)';

    public function __construct(
        private ChartBuilderInterface $chartBuilder,
    ) {
    }

    public function line(ObligationSeries $series): Chart
    {
        // Every point is in the display currency, so the first one fixes how the axis and tooltip format money.
        $displayCurrency = $series->points[0]->amount->currency ?? null;

        $chart = $this->chartBuilder->createChart(Chart::TYPE_LINE);

        $chart->setData([
            'labels' => array_map(static fn (ObligationPoint $point): string => $point->label, $series->points),
            'datasets' => [[
                'label' => 'Obligation',
                'data' => array_map(static fn (ObligationPoint $point): int => $point->amount->minorAmount, $series->points),
                'borderColor' => self::LINE_COLOUR,
                'backgroundColor' => self::FILL_COLOUR,
                'fill' => true,
                'tension' => 0.3,
                // Custom payload read by the obligation-trend Stimulus controller for the tooltip and y-axis.
                'displayAmounts' => array_map(static fn (ObligationPoint $point): string => $point->amount->format(), $series->points),
                'currencySymbol' => $displayCurrency?->symbol() ?? '',
                'fractionDigits' => $displayCurrency?->fractionDigits() ?? 2,
            ]],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ]);

        return $chart;
    }
}
