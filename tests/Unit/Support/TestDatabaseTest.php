<?php

// ABOUTME: Unit tests for TestDatabase, the helper the PHPUnit bootstrap uses to rebuild app_test.
// ABOUTME: Pins the invariant that a failed rebuild step aborts loudly instead of running on stale data.

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\NullOutput;

final class TestDatabaseTest extends TestCase
{
    private const array STEPS = [
        'doctrine:database:drop',
        'doctrine:database:create',
        'doctrine:migrations:migrate',
    ];

    /**
     * Every step has to be checked. A drop that fails leaves the previous run's committed rows in place,
     * and the create and migrate that follow are then both no-ops on an already-current database - so the
     * suite would run against stale data while reporting nothing wrong.
     */
    #[DataProvider('provideRebuildAbortsWhenAStepFailsCases')]
    public function testRebuildAbortsWhenAStepFails(string $failingStep): void
    {
        $ran = [];
        $database = new TestDatabase($this->application($ran, $failingStep), new NullOutput());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains($failingStep);

        $database->rebuild();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRebuildAbortsWhenAStepFailsCases(): iterable
    {
        foreach (self::STEPS as $step) {
            yield $step => [$step];
        }
    }

    public function testRebuildDropsCreatesAndMigratesInOrder(): void
    {
        $ran = [];
        $database = new TestDatabase($this->application($ran), new NullOutput());

        $database->rebuild();

        self::assertSame(self::STEPS, $ran);
    }

    public function testRebuildStopsAtTheFirstFailedStep(): void
    {
        $ran = [];
        $database = new TestDatabase($this->application($ran, 'doctrine:database:drop'), new NullOutput());

        try {
            $database->rebuild();
        } catch (\RuntimeException) {
            // Asserted on below: the point is which steps ran, not the message.
        }

        self::assertSame(['doctrine:database:drop'], $ran);
    }

    /**
     * A console application standing in for the real one, recording each step it runs into $ran and
     * failing the named step. Validation is ignored so the fakes need not mirror the real commands' options.
     *
     * @param list<string> $ran
     */
    private function application(array &$ran, string $failingStep = ''): Application
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        foreach (self::STEPS as $step) {
            $command = new Command($step);
            $command->setCode(static function () use ($step, $failingStep, &$ran): int {
                $ran[] = $step;

                return $step === $failingStep ? Command::FAILURE : Command::SUCCESS;
            });
            $command->ignoreValidationErrors();

            $application->addCommand($command);
        }

        return $application;
    }
}
