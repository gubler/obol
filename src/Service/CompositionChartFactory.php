<?php

// ABOUTME: Turns a Composition into a Chart.js pie definition for rendering with render_chart().
// ABOUTME: Carries display-currency amounts and per-slice native breakdown lines for the custom tooltip.

declare(strict_types=1);

namespace App\Service;

use App\Message\Query\Report\Composition;
use App\Message\Query\Report\CompositionSlice;
use App\ValueObject\Money;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final readonly class CompositionChartFactory
{
    /**
     * A fixed wheel of slice colours, cycled when there are more slices than entries.
     *
     * @var list<string>
     */
    private const array PALETTE = [
        '#6366f1', // indigo
        '#22c55e', // green
        '#f59e0b', // amber
        '#ef4444', // red
        '#06b6d4', // cyan
        '#a855f7', // purple
        '#ec4899', // pink
        '#84cc16', // lime
        '#f97316', // orange
        '#14b8a6', // teal
    ];

    public function __construct(
        private ChartBuilderInterface $chartBuilder,
    ) {
    }

    public function pie(Composition $composition): Chart
    {
        $chart = $this->chartBuilder->createChart(Chart::TYPE_PIE);

        $chart->setData([
            'labels' => array_map(static fn (CompositionSlice $slice): string => $slice->label, $composition->slices),
            'datasets' => [[
                'data' => array_map(static fn (CompositionSlice $slice): int => $slice->converted->minorAmount, $composition->slices),
                'backgroundColor' => $this->sliceColours($composition->slices),
                // Custom payload read by the composition-pie Stimulus controller's tooltip callbacks.
                'displayAmounts' => array_map(static fn (CompositionSlice $slice): string => $slice->converted->format(), $composition->slices),
                'nativeBreakdown' => array_map(
                    static fn (CompositionSlice $slice): array => $slice->isApproximate
                        ? array_map(static fn (Money $money): string => $money->format(), $slice->breakdown)
                        : [],
                    $composition->slices,
                ),
            ]],
        ]);

        $chart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'right'],
            ],
        ]);

        return $chart;
    }

    /**
     * Wedge fills: each slice with a category color uses that color's hex (so the wedge matches its
     * flat swatch in the legend); leaf slices without a color cycle the fixed palette.
     *
     * @param list<CompositionSlice> $slices
     *
     * @return list<string>
     */
    private function sliceColours(array $slices): array
    {
        return array_map(
            fn (CompositionSlice $slice, int $index): string => $slice->color?->baseColorHex()
                ?? self::PALETTE[$index % \count(self::PALETTE)],
            $slices,
            array_keys($slices),
        );
    }
}
