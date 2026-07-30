<?php

// ABOUTME: Architecture tests over the production logging configuration, asserting Monolog hands its
// ABOUTME: output to the container rather than writing files nothing collects. See reference/adr/0026.

declare(strict_types=1);

namespace App\Tests\Arch;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ProductionLoggingTest extends TestCase
{
    private const string MONOLOG_CONFIG = __DIR__ . '/../../config/packages/monolog.yaml';

    /**
     * Production's `var/log` is ephemeral: the image declares no volume and the stack mounts nothing
     * but the database's, so anything written there dies with the container (reference/adr/0026). A
     * file handler in production therefore rotates logs nobody will read, and discards them on
     * exactly the event - a recreate - you most want to read them after.
     */
    public function testProductionWritesNoLogFiles(): void
    {
        foreach (self::productionHandlers() as $name => $handler) {
            self::assertNotSame(
                'rotating_file',
                $handler['type'] ?? null,
                \sprintf('The "%s" production handler writes to a file, which dies with the container.', $name),
            );

            self::assertStringNotContainsString(
                'kernel.logs_dir',
                (string) ($handler['path'] ?? ''),
                \sprintf('The "%s" production handler writes under var/log, which is ephemeral.', $name),
            );
        }
    }

    /**
     * The container's own output is the only destination the log driver collects, and the driver is
     * what carries production logs to the host journal where they outlive a recreate. stderr rather
     * than stdout because that is where a process is expected to put diagnostics, so log lines stay
     * distinguishable from whatever a console command writes as its actual output.
     */
    public function testProductionLogsToStandardError(): void
    {
        $destinations = [];

        foreach (self::productionHandlers() as $name => $handler) {
            if (!isset($handler['path'])) {
                continue;
            }

            $destinations[$name] = $handler['path'];
        }

        self::assertNotSame([], $destinations, 'No production handler writes anywhere.');

        foreach ($destinations as $name => $path) {
            self::assertSame(
                'php://stderr',
                $path,
                \sprintf('The "%s" production handler does not write to the container.', $name),
            );
        }
    }

    /**
     * Returns the handler map the production environment adds, keyed by handler name.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function productionHandlers(): array
    {
        /** @var array<string, mixed> $config */
        $config = Yaml::parseFile(self::MONOLOG_CONFIG);

        $handlers = $config['when@prod']['monolog']['handlers'] ?? null;

        self::assertIsArray($handlers, 'The Monolog configuration declares no production handlers.');

        /* @var array<string, array<string, mixed>> $handlers */
        return $handlers;
    }
}
