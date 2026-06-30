<?php

// ABOUTME: Runner for FindObligationOverTimeQuery: the obligation trend read from the ObligationSnapshot series.
// ABOUTME: Each bucket carries the latest snapshot on or before its start, converted to the display currency at today's rate.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Entity\ObligationSnapshot;
use App\Enum\Currency;
use App\Message\Currency\CurrencyTotaller;
use App\Repository\ObligationSnapshotRepository;
use App\Service\PeriodBoundaries;
use App\ValueObject\Money;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindObligationOverTimeQuery::class)]
final readonly class FindObligationOverTimeRunner
{
    public function __construct(
        private ObligationSnapshotRepository $snapshotRepository,
        private CurrencyTotaller $currencyTotaller,
        private PeriodBoundaries $periodBoundaries,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(FindObligationOverTimeQuery $query): ObligationSeries
    {
        $now = $this->clock->now();
        $snapshots = $this->snapshotRepository->findAllOrderedByRecordedAt();

        $points = [];
        $approximate = false;
        foreach ($this->anchors($query, $now) as $anchor) {
            $snapshot = $this->latestOnOrBefore($snapshots, $anchor);
            $total = $this->currencyTotaller->total($this->nativeAmounts($snapshot));
            $approximate = $approximate || $total->isApproximate;

            $points[] = new ObligationPoint(
                label: $anchor->format($query->period->labelFormat()),
                amount: $total->converted,
            );
        }

        return new ObligationSeries(
            points: $points,
            period: $query->period,
            asOf: $now,
            isApproximate: $approximate,
        );
    }

    /**
     * The start-of-period anchors, oldest first, ending with the current in-progress bucket.
     *
     * @return list<\DateTimeImmutable>
     */
    private function anchors(FindObligationOverTimeQuery $query, \DateTimeImmutable $now): array
    {
        $period = $query->period;

        $anchors = [];
        for ($stepsBack = $period->lookback() - 1; $stepsBack >= 0; --$stepsBack) {
            $anchors[] = $this->periodBoundaries->startOfPeriod(
                $period,
                $now->modify(\sprintf('-%d %s', $stepsBack, $period->stepUnit())),
            );
        }

        return $anchors;
    }

    /**
     * The latest snapshot recorded on or before `$anchor`, or null when none precedes it. `$snapshots` is
     * oldest first, so the last match wins.
     *
     * @param list<ObligationSnapshot> $snapshots
     */
    private function latestOnOrBefore(array $snapshots, \DateTimeImmutable $anchor): ?ObligationSnapshot
    {
        $carried = null;
        foreach ($snapshots as $snapshot) {
            if ($snapshot->recordedAt > $anchor) {
                break;
            }

            $carried = $snapshot;
        }

        return $carried;
    }

    /**
     * The snapshot's native per-currency map as a list of Money, or an empty list when there is no snapshot.
     *
     * @return list<Money>
     */
    private function nativeAmounts(?ObligationSnapshot $snapshot): array
    {
        if (!$snapshot instanceof ObligationSnapshot) {
            return [];
        }

        return array_map(
            static fn (int $minor, string $code): Money => new Money($minor, Currency::from($code)),
            array_values($snapshot->obligationsByCurrency),
            array_keys($snapshot->obligationsByCurrency),
        );
    }
}
