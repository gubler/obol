<?php

// ABOUTME: Twig extension labeling a subscription's next-renewal line by calendar day.
// ABOUTME: Today/Tomorrow for the near term (clock-driven, time-of-day independent), else KnpTime's time_diff.

declare(strict_types=1);

namespace App\Twig;

use Knp\Bundle\TimeBundle\DateTimeFormatter;
use Psr\Clock\ClockInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class RenewalLabelExtension extends AbstractExtension
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly TranslatorInterface $translator,
        private readonly DateTimeFormatter $dateTimeFormatter,
    ) {
    }

    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('renewal_label', $this->label(...)),
        ];
    }

    /**
     * The renewal date is compared by calendar day against "now" so the label does not flip with the
     * time of day: a renewal anchored at midnight today reads "Today" whether the page loads at 1am or
     * 11pm. Two or more days out (or any past date) falls back to KnpTime's time_diff, fed the same
     * midnight-aligned days so its count is the calendar-day distance (the renewal day itself counts,
     * since it is a valid not-late payment day) rather than the time-of-day-truncated wall-clock gap.
     */
    public function label(\DateTimeImmutable $renewal): string
    {
        $now = $this->clock->now();
        $today = \DateTimeImmutable::createFromInterface($now)->setTime(0, 0);
        $renewalDay = \DateTimeImmutable::createFromInterface($renewal)->setTime(0, 0);

        $days = (int) $today->diff($renewalDay)->format('%r%a');

        return match ($days) {
            0 => $this->translator->trans('common.relative.today'),
            1 => $this->translator->trans('common.relative.tomorrow'),
            default => $this->dateTimeFormatter->formatDiff($renewalDay, $today),
        };
    }
}
