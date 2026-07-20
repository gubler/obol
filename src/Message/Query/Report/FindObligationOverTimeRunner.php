<?php

// ABOUTME: Runner for FindObligationOverTimeQuery: the obligation trend read from the ObligationSnapshot series.
// ABOUTME: Each bucket carries the latest snapshot on or before its start, converted to the display currency at today's rate.

declare(strict_types=1);

namespace App\Message\Query\Report;

use App\Entity\ObligationSnapshot;
use App\Enum\Currency;
use App\Enum\ObligationTrendPeriod;
use App\Message\Currency\CurrencyTotaller;
use App\Repository\ObligationSnapshotRepository;
use App\Repository\UserRepository;
use App\Service\PeriodBoundaries;
use App\ValueObject\CalendarDate;
use App\ValueObject\Money;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: FindObligationOverTimeQuery::class)]
final readonly class FindObligationOverTimeRunner
{
    public function __construct(
        private ObligationSnapshotRepository $snapshotRepository,
        private CurrencyTotaller $currencyTotaller,
        private UserRepository $userRepository,
        private PeriodBoundaries $periodBoundaries,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(FindObligationOverTimeQuery $query): ObligationSeries
    {
        // Resolve today in the owner's timezone so the period anchors and their labels bucket the series
        // on the owner's local calendar; the stored recordedAt dates are calendar dates too (ADR-0016).
        $owner = $this->userRepository->getForId($query->ownerUserId);
        $today = $owner->localDateFor($this->clock->now());
        $display = $owner->displayCurrency;
        $snapshots = $this->snapshotRepository->findAllOrderedByRecordedAtForOwner($query->ownerUserId);

        $points = [];
        $approximate = false;
        foreach ($this->anchors($query, $today) as $anchor) {
            $snapshot = $this->latestOnOrBefore($snapshots, $anchor);
            $total = $this->currencyTotaller->total($this->nativeAmounts($snapshot), $display);
            $approximate = $approximate || $total->isApproximate;

            $points[] = new ObligationPoint(
                label: $this->label($anchor, $query->period),
                amount: $total->converted,
            );
        }

        return new ObligationSeries(
            points: $points,
            period: $query->period,
            asOf: $today,
            isApproximate: $approximate,
        );
    }

    /**
     * The start-of-period anchors, oldest first, ending with the current in-progress bucket. Stepping is
     * done in calendar terms (whole weeks, or month/year ordinals) so a short month never drifts a bucket.
     *
     * @return list<CalendarDate>
     */
    private function anchors(FindObligationOverTimeQuery $query, CalendarDate $today): array
    {
        $period = $query->period;
        $current = $this->periodBoundaries->startOfPeriod($period, $today);

        $anchors = [];
        for ($stepsBack = $period->lookback() - 1; $stepsBack >= 0; --$stepsBack) {
            $anchors[] = $this->stepBack($period, $current, $stepsBack);
        }

        return $anchors;
    }

    /**
     * The start-of-period anchor `$steps` buckets before `$current` (itself a period start).
     */
    private function stepBack(ObligationTrendPeriod $period, CalendarDate $current, int $steps): CalendarDate
    {
        return match ($period) {
            ObligationTrendPeriod::Week => $current->plusWeeks(-$steps),
            ObligationTrendPeriod::Month => $this->firstOfMonth($current->monthOrdinal() - $steps),
            ObligationTrendPeriod::Year => CalendarDate::for($current->year - $steps, 1, 1),
        };
    }

    /**
     * The first day of the month named by the given ordinal (`year * 12 + month - 1`).
     */
    private function firstOfMonth(int $monthOrdinal): CalendarDate
    {
        return CalendarDate::for(intdiv($monthOrdinal, 12), $monthOrdinal % 12 + 1, 1);
    }

    /**
     * A bucket's x-axis label, rendered from the calendar date via a fixed (locale-independent) pattern.
     */
    private function label(CalendarDate $anchor, ObligationTrendPeriod $period): string
    {
        return $anchor->toDateTimeImmutable(new \DateTimeZone('UTC'))->format($period->labelFormat());
    }

    /**
     * The latest snapshot recorded on or before `$anchor`, or null when none precedes it. `$snapshots` is
     * oldest first, so the last match wins.
     *
     * @param list<ObligationSnapshot> $snapshots
     */
    private function latestOnOrBefore(array $snapshots, CalendarDate $anchor): ?ObligationSnapshot
    {
        $carried = null;
        foreach ($snapshots as $snapshot) {
            if ($snapshot->recordedAt->isAfter($anchor)) {
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
