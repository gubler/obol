<?php

// ABOUTME: Rebuilds the test database from migrations for the PHPUnit bootstrap, one console step at a time.
// ABOUTME: Aborts on the first failed step so the suite can never run against a database left over from an earlier run.

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class TestDatabase
{
    public function __construct(
        private Application $application,
        private OutputInterface $output,
    ) {
    }

    /**
     * Drop, create, migrate - in that order, each one checked. The suite's isolation rests entirely on
     * this: DAMA rolls back the transaction around each test, but the Panther tests opt out of that
     * rollback and commit, so a database that survives into the next run carries their writes with it.
     */
    public function rebuild(): void
    {
        $this->run('doctrine:database:drop', ['--force' => true, '--if-exists' => true]);
        $this->run('doctrine:database:create', ['--if-not-exists' => true]);
        $this->run('doctrine:migrations:migrate', ['--no-interaction' => true]);
    }

    /**
     * @param array<string, bool|string> $parameters
     */
    private function run(string $command, array $parameters): void
    {
        $input = new ArrayInput(['command' => $command, ...$parameters]);
        $input->setInteractive(false);

        $exitCode = $this->application->run($input, $this->output);

        if (Command::SUCCESS !== $exitCode) {
            throw new \RuntimeException(\sprintf('Could not rebuild the test database: "%s" exited with %d. Aborting: the alternative is to run the suite against whatever the last run left behind, which fails in ways that look like product bugs. A drop fails when something still holds a connection to the database - usually a Panther server orphaned by an interrupted run. docs/src/content/docs/development/testing.md has the recovery command.', $command, $exitCode));
        }
    }
}
