<?php

// ABOUTME: Architecture tests over the container image definition, scanning the Dockerfile's build
// ABOUTME: stages for the storage rules production depends on. See reference/adr/0026.

declare(strict_types=1);

namespace App\Tests\Arch;

use PHPUnit\Framework\TestCase;

final class ProductionImageTest extends TestCase
{
    private const string DOCKERFILE = __DIR__ . '/../../Dockerfile';

    /**
     * Matches the instruction at the start of a line, so a stage may still explain in a comment why
     * it declares none.
     */
    private const string VOLUME_INSTRUCTION = '/^VOLUME\b/m';

    /**
     * The production image must own no storage. A VOLUME here is anonymous - no compose file names a
     * volume at that path - and Compose reuses anonymous volumes when it recreates a container, so
     * the previous release's compiled cache and built CSS would shadow the ones baked into the new
     * image and the upgrade would silently not take effect. Everything that has to survive a
     * container lives in PostgreSQL instead.
     */
    public function testTheProductionStageDeclaresNoVolume(): void
    {
        self::assertDoesNotMatchRegularExpression(self::VOLUME_INSTRUCTION, self::stage('frankenphp_prod'));
    }

    /**
     * The other side of the same rule: the base stage's volume is load-bearing in development, where
     * the Tailwind sidecar and php share compiled CSS through an explicit var/tailwind volume that
     * exists precisely because each container otherwise gets its own var/. Removing it would leave
     * php never seeing the sidecar's builds.
     */
    public function testTheBaseStageKeepsTheVolumeTheDevelopmentSidecarNeeds(): void
    {
        self::assertStringContainsString('VOLUME /app/var/', self::stage('frankenphp_base'));
    }

    /**
     * Returns the body of one named build stage, from its FROM line to the next one.
     */
    private static function stage(string $name): string
    {
        $dockerfile = file_get_contents(self::DOCKERFILE);
        self::assertIsString($dockerfile);

        $stages = preg_split('/^FROM .+ AS (\S+)$/m', $dockerfile, -1, \PREG_SPLIT_DELIM_CAPTURE);
        self::assertIsArray($stages);

        $index = array_search($name, $stages, true);
        self::assertIsInt($index, \sprintf('The Dockerfile declares no "%s" stage.', $name));

        return $stages[$index + 1];
    }
}
