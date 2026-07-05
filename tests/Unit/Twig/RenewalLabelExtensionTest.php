<?php

// ABOUTME: Unit tests for RenewalLabelExtension's calendar-day labeling of a subscription's renewal.
// ABOUTME: Pins the clock so the today/tomorrow boundary is deterministic regardless of the time of day.

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Entity\User;
use App\Twig\RenewalLabelExtension;
use Knp\Bundle\TimeBundle\DateTimeFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RenewalLabelExtensionTest extends TestCase
{
    public function testRenewalDatedTodayReadsTodayRegardlessOfTimeOfDay(): void
    {
        $renewal = new \DateTimeImmutable('2026-06-18 00:00:00', new \DateTimeZone('UTC'));

        // Early morning and late evening on the same calendar day both read "Today".
        self::assertSame('Today', $this->extensionAt('2026-06-18 00:30:00')->label($renewal, self::utcOwner()));
        self::assertSame('Today', $this->extensionAt('2026-06-18 23:30:00')->label($renewal, self::utcOwner()));
    }

    public function testRenewalDatedTomorrowReadsTomorrow(): void
    {
        $renewal = new \DateTimeImmutable('2026-06-19 00:00:00', new \DateTimeZone('UTC'));

        self::assertSame('Tomorrow', $this->extensionAt('2026-06-18 23:30:00')->label($renewal, self::utcOwner()));
    }

    public function testRenewalTwoOrMoreDaysOutDelegatesToTimeDiff(): void
    {
        $renewal = new \DateTimeImmutable('2026-06-20 00:00:00', new \DateTimeZone('UTC'));

        self::assertSame('diff.in.day:2', $this->extensionAt('2026-06-18 12:00:00')->label($renewal, self::utcOwner()));
    }

    public function testPastRenewalDelegatesToTimeDiff(): void
    {
        $renewal = new \DateTimeImmutable('2026-06-17 00:00:00', new \DateTimeZone('UTC'));

        self::assertSame('diff.ago.day:1', $this->extensionAt('2026-06-18 12:00:00')->label($renewal, self::utcOwner()));
    }

    public function testDayCountIncludesTheRenewalDayAndIgnoresTimeOfDay(): void
    {
        // June 29 -> July 22 is 23 calendar days: today is not counted, the renewal day is, since
        // it is itself a valid (not late) payment day. The count must not be truncated by the time
        // of day the page is viewed - morning and evening on the same day read the same.
        $renewal = new \DateTimeImmutable('2026-07-22 00:00:00', new \DateTimeZone('UTC'));

        self::assertSame('diff.in.day:23', $this->extensionAt('2026-06-29 01:00:00')->label($renewal, self::utcOwner()));
        self::assertSame('diff.in.day:23', $this->extensionAt('2026-06-29 23:00:00')->label($renewal, self::utcOwner()));
    }

    public function testLongerHorizonsStillRenderInCoarserUnits(): void
    {
        // The fix pins the day count to calendar days without flattening KnpTime's coarser phrasing:
        // a renewal two months out still reads in months, not in days.
        $renewal = new \DateTimeImmutable('2026-08-29 00:00:00', new \DateTimeZone('UTC'));

        self::assertSame('diff.in.month:2', $this->extensionAt('2026-06-29 12:00:00')->label($renewal, self::utcOwner()));
    }

    public function testResolvesTodayInTheOwnersTimezoneNotUtc(): void
    {
        // A renewal dated June 18, viewed at 02:00 UTC on June 18. For a UTC owner that is "Today"; for a
        // Honolulu owner (UTC-10) it is still June 17 locally, so the June 18 renewal reads "Tomorrow".
        $renewal = new \DateTimeImmutable('2026-06-18 00:00:00', new \DateTimeZone('UTC'));
        $honolulu = new User(email: 'hi@example.com', timezone: 'Pacific/Honolulu');

        self::assertSame('Today', $this->extensionAt('2026-06-18 02:00:00')->label($renewal, self::utcOwner()));
        self::assertSame('Tomorrow', $this->extensionAt('2026-06-18 02:00:00')->label($renewal, $honolulu));
    }

    private static function utcOwner(): User
    {
        return new User(email: 'utc@example.com', timezone: 'UTC');
    }

    private function extensionAt(string $now): RenewalLabelExtension
    {
        $translator = $this->fakeTranslator();

        return new RenewalLabelExtension(
            new MockClock(new \DateTimeImmutable($now, new \DateTimeZone('UTC'))),
            $translator,
            new DateTimeFormatter($translator),
        );
    }

    /**
     * Resolves the today/tomorrow catalog keys to their English copy and renders any KnpTime
     * `time` diff message as "<id>:<count>", so a delegated label exposes both the unit and the
     * day count in assertions (e.g. "diff.in.day:23") without depending on the bundle's copy.
     */
    private function fakeTranslator(): TranslatorInterface
    {
        return new class implements TranslatorInterface {
            /**
             * @param array<string, mixed> $parameters
             */
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                if ('time' === $domain) {
                    $count = $parameters['%count%'] ?? null;

                    return null === $count ? $id : \sprintf('%s:%s', $id, $count);
                }

                return match ($id) {
                    'common.relative.today' => 'Today',
                    'common.relative.tomorrow' => 'Tomorrow',
                    default => $id,
                };
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };
    }
}
