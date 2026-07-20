<?php

// ABOUTME: Twig `user_date` / `user_datetime` filters rendering per the user's DateFormat preference.
// ABOUTME: Delegate to DateFormatter; anonymous requests fall back to the Medium style.

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Enum\DateFormat;
use App\Service\DateFormatter;
use App\ValueObject\CalendarDate;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class UserDateExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly DateFormatter $dateFormatter,
    ) {
    }

    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('user_date', $this->format(...)),
            new TwigFilter('user_datetime', $this->formatDateTime(...)),
        ];
    }

    public function format(\DateTimeInterface|CalendarDate $date): string
    {
        // user_date renders both a calendar date (nextRenewal, paidDate, report as-of dates) and a plain
        // instant (createdAt, lastUsedAt) in the user's style; user_datetime below stays instant-only.
        return $date instanceof CalendarDate
            ? $this->dateFormatter->formatCalendarDate($date, $this->preference())
            : $this->dateFormatter->format($date, $this->preference());
    }

    public function formatDateTime(\DateTimeInterface $dateTime): string
    {
        return $this->dateFormatter->formatDateTime($dateTime, $this->preference());
    }

    private function preference(): DateFormat
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->dateFormat : DateFormat::Medium;
    }
}
