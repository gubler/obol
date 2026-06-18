<?php

// ABOUTME: Unit tests for RenewalLabelExtension's calendar-day labeling of a subscription's renewal.
// ABOUTME: Pins the clock so the today/tomorrow boundary is deterministic regardless of the time of day.

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

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
        self::assertSame('Today', $this->extensionAt('2026-06-18 00:30:00')->label($renewal));
        self::assertSame('Today', $this->extensionAt('2026-06-18 23:30:00')->label($renewal));
    }

    public function testRenewalDatedTomorrowReadsTomorrow(): void
    {
        $renewal = new \DateTimeImmutable('2026-06-19 00:00:00', new \DateTimeZone('UTC'));

        self::assertSame('Tomorrow', $this->extensionAt('2026-06-18 23:30:00')->label($renewal));
    }

    public function testRenewalTwoOrMoreDaysOutDelegatesToTimeDiff(): void
    {
        $renewal = new \DateTimeImmutable('2026-06-20 00:00:00', new \DateTimeZone('UTC'));

        self::assertSame('TIME_DIFF', $this->extensionAt('2026-06-18 12:00:00')->label($renewal));
    }

    public function testPastRenewalDelegatesToTimeDiff(): void
    {
        $renewal = new \DateTimeImmutable('2026-06-17 00:00:00', new \DateTimeZone('UTC'));

        self::assertSame('TIME_DIFF', $this->extensionAt('2026-06-18 12:00:00')->label($renewal));
    }

    private function extensionAt(string $now): RenewalLabelExtension
    {
        $translator = $this->fakeTranslator();

        return new RenewalLabelExtension(
            new MockClock($now),
            $translator,
            new DateTimeFormatter($translator),
        );
    }

    /**
     * Resolves the today/tomorrow catalog keys to their English copy and collapses any KnpTime
     * `time` diff message to a sentinel, so a delegated label is unambiguous in assertions.
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
                    return 'TIME_DIFF';
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
