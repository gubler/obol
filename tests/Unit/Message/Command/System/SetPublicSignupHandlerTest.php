<?php

// ABOUTME: Unit tests for SetPublicSignupHandler - flips the singleton's public-signup flag and flushes.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\System;

use App\Entity\SystemSettings;
use App\Message\Command\System\SetPublicSignupCommand;
use App\Message\Command\System\SetPublicSignupHandler;
use App\Repository\SystemSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class SetPublicSignupHandlerTest extends TestCase
{
    public function testEnablesPublicSignupAndFlushes(): void
    {
        $settings = new SystemSettings(publicSignupEnabled: false);

        $handler = $this->handlerFor($settings, flushExpected: true);
        $handler(new SetPublicSignupCommand(enabled: true));

        self::assertTrue($settings->publicSignupEnabled);
    }

    public function testDisablesPublicSignupAndFlushes(): void
    {
        $settings = new SystemSettings(publicSignupEnabled: true);

        $handler = $this->handlerFor($settings, flushExpected: true);
        $handler(new SetPublicSignupCommand(enabled: false));

        self::assertFalse($settings->publicSignupEnabled);
    }

    private function handlerFor(SystemSettings $settings, bool $flushExpected): SetPublicSignupHandler
    {
        $repository = $this->createMock(SystemSettingsRepository::class);
        $repository->expects(self::once())->method('get')->willReturn($settings);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($flushExpected ? self::once() : self::never())->method('flush');

        return new SetPublicSignupHandler($repository, $entityManager);
    }
}
