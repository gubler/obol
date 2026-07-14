<?php

// ABOUTME: Unit test for GetSystemSettingsRunner - returns the singleton the repository resolves.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Query\System;

use App\Entity\SystemSettings;
use App\Message\Query\System\GetSystemSettingsQuery;
use App\Message\Query\System\GetSystemSettingsRunner;
use App\Repository\SystemSettingsRepository;
use PHPUnit\Framework\TestCase;

final class GetSystemSettingsRunnerTest extends TestCase
{
    public function testReturnsTheSettingsFromTheRepository(): void
    {
        $settings = new SystemSettings();
        $repository = $this->createMock(SystemSettingsRepository::class);
        $repository->expects(self::once())->method('get')->willReturn($settings);

        $runner = new GetSystemSettingsRunner($repository);

        self::assertSame($settings, $runner(new GetSystemSettingsQuery()));
    }
}
