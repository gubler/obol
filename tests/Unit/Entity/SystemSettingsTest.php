<?php

// ABOUTME: Unit tests for the SystemSettings singleton - the app-global operator-controlled configuration.
// ABOUTME: Covers the default (public signup off) and the typed toggle mutator.

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\SystemSettings;
use PHPUnit\Framework\TestCase;

final class SystemSettingsTest extends TestCase
{
    public function testIsTheSingletonRowWithIdOne(): void
    {
        self::assertSame(1, new SystemSettings()->id);
    }

    public function testDefaultsToPublicSignupDisabled(): void
    {
        // A fresh deploy is closed by default: strangers cannot self-register until an operator opts in.
        self::assertFalse(new SystemSettings()->publicSignupEnabled);
    }

    public function testChangePublicSignupFlipsTheFlag(): void
    {
        $settings = new SystemSettings();

        $settings->changePublicSignup(true);
        self::assertTrue($settings->publicSignupEnabled);

        $settings->changePublicSignup(false);
        self::assertFalse($settings->publicSignupEnabled);
    }
}
