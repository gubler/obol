<?php

// ABOUTME: Architecture test over the CI workflow's release job: every image it publishes is built on
// ABOUTME: freshly pulled base images rather than whatever the runner has cached.

declare(strict_types=1);

namespace App\Tests\Arch;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ReleaseWorkflowTest extends TestCase
{
    private const string WORKFLOW = __DIR__ . '/../../.gitea/workflows/ci.yml';

    /**
     * The base images float on tags such as `1-php8.5`, which move with each PHP and FrankenPHP security
     * release. A runner that already has the tag cached would otherwise build on the old image and ship
     * it with nothing to say so.
     */
    public function testEveryReleaseImageIsBuiltOnFreshlyPulledBaseImages(): void
    {
        $buildSteps = array_filter(
            self::releaseSteps(),
            static fn (array $step): bool => str_starts_with((string) ($step['uses'] ?? ''), 'docker/build-push-action@'),
        );

        self::assertNotEmpty($buildSteps, 'The release job builds no image.');

        foreach ($buildSteps as $step) {
            self::assertTrue($step['with']['pull'] ?? false, \sprintf('The %s image may be built on a stale cached base image.', $step['with']['target'] ?? 'untargeted'));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function releaseSteps(): array
    {
        $workflow = Yaml::parseFile(self::WORKFLOW);
        self::assertIsArray($workflow);

        $steps = $workflow['jobs']['build']['steps'] ?? null;
        self::assertIsArray($steps);

        return $steps;
    }
}
