<?php

// ABOUTME: Unit tests for Schedule verifying scheduler configuration.
// ABOUTME: Tests that schedule returns a valid Schedule instance with recurring messages.

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Message\Scheduler\GeneratePaymentsMessage;
use App\Message\Scheduler\PruneExpiredCacheItemsMessage;
use App\Message\Scheduler\PullExchangeRatesMessage;
use App\Schedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Contracts\Cache\CacheInterface;

final class ScheduleTest extends TestCase
{
    public function testScheduleReturnsASymfonyScheduleInstance(): void
    {
        $cache = self::createStub(CacheInterface::class);
        $schedule = new Schedule($cache);

        $result = $schedule->getSchedule();

        self::assertInstanceOf(SymfonySchedule::class, $result);
    }

    public function testScheduleRunsPaymentGenerationHourlyAndTheOthersDaily(): void
    {
        // Payment generation is hourly so each timezone's local-midnight rollover is caught within the
        // hour (ADR-0016); the exchange-rate pull and the cache prune stay daily. Keyed by message
        // rather than collected into a flat list, so a job cannot silently take another's cadence.
        self::assertSame(
            [
                GeneratePaymentsMessage::class => 'every 1 hour',
                PruneExpiredCacheItemsMessage::class => 'every 1 day',
                PullExchangeRatesMessage::class => 'every 1 day',
            ],
            $this->cadencesByMessage(),
        );
    }

    /**
     * Expired rows are removed by the scheduler and nothing else - no host cron entry, and no
     * opportunistic cleanup that reaches rows a read never touches.
     */
    public function testTheCachePruneIsOnTheSchedule(): void
    {
        self::assertArrayHasKey(PruneExpiredCacheItemsMessage::class, $this->cadencesByMessage());
    }

    /**
     * @return array<class-string, string>
     */
    private function cadencesByMessage(): array
    {
        $schedule = new Schedule(self::createStub(CacheInterface::class))->getSchedule();

        $cadences = [];
        foreach ($schedule->getRecurringMessages() as $recurringMessage) {
            // A recurring message yields its payload through a provider rather than exposing it, and
            // the static provider behind every entry here ignores the context it is handed.
            $context = new MessageContext(
                'schedule-test',
                $recurringMessage->getId(),
                $recurringMessage->getTrigger(),
                new \DateTimeImmutable(),
            );

            foreach ($recurringMessage->getMessages($context) as $message) {
                $cadences[$message::class] = (string) $recurringMessage->getTrigger();
            }
        }

        ksort($cadences);

        return $cadences;
    }
}
