<?php

// ABOUTME: Integration test that the development Caddy configuration - the shared Caddyfile plus the dev
// ABOUTME: overlay's injected directives - validates (parses and provisions) under the suite's FrankenPHP.

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class DevelopmentCaddyfileTest extends TestCase
{
    private const string CADDYFILE = __DIR__ . '/../../frankenphp/Caddyfile';

    private const string DEV_OVERLAY = __DIR__ . '/../../compose.dev.yaml';

    /**
     * The base image floats on `dunglas/frankenphp:1-php8.5`, so a release can change the Caddy modules
     * under an unchanged repository - the Mercure hub the dev overlay injects for hot_reload is the
     * likeliest casualty. A configuration that no longer validates leaves the dev php container restarting
     * with nothing but an exit code, so this surfaces it here, with Caddy's own error, instead. Validating
     * rather than only adapting matters: a module can accept a directive's syntax and still refuse it when
     * provisioned, as Mercure 1.0 does with top-level publisher_jwt.
     */
    public function testTheDevelopmentConfigurationValidates(): void
    {
        $result = self::validate(self::developmentEnvironment());

        self::assertSame(0, $result['exitCode'], 'The development Caddy configuration does not validate: ' . $result['stderr']);
    }

    /**
     * @return array<string, string>
     */
    private static function developmentEnvironment(): array
    {
        $overlay = Yaml::parseFile(self::DEV_OVERLAY);
        self::assertIsArray($overlay);

        $environment = $overlay['services']['php']['environment'] ?? null;
        self::assertIsArray($environment);

        return array_map(strval(...), $environment);
    }

    /**
     * Validation runs with its own Caddy data and config directories. Provisioning the hub opens its
     * transport's store under the data directory, and in the dev php container the running server
     * already holds that store open, so sharing it would fail on a lock rather than on the configuration.
     *
     * @param array<string, string> $environment
     *
     * @return array{exitCode: int, stderr: string}
     */
    private static function validate(array $environment): array
    {
        $storage = sys_get_temp_dir() . '/caddyfile-test-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($storage . '/data', 0o700, true) && mkdir($storage . '/config', 0o700));

        try {
            $process = proc_open(
                ['frankenphp', 'validate', '--config', self::CADDYFILE],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                null,
                ['XDG_DATA_HOME' => $storage . '/data', 'XDG_CONFIG_HOME' => $storage . '/config'] + $environment + getenv(),
            );
            self::assertIsResource($process, 'frankenphp could not be started.');

            stream_get_contents($pipes[1]);
            $stderr = (string) stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            return ['exitCode' => proc_close($process), 'stderr' => $stderr];
        } finally {
            self::remove($storage);
        }
    }

    private static function remove(string $directory): void
    {
        $entries = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            self::assertInstanceOf(\SplFileInfo::class, $entry);

            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }

        rmdir($directory);
    }
}
