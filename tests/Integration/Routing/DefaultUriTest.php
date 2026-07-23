<?php

// ABOUTME: Guards that off-request URL generation (CLI/worker context) uses the configured DEFAULT_URI
// ABOUTME: domain instead of falling back to http://localhost, so worker-minted magic links resolve.

declare(strict_types=1);

namespace App\Tests\Integration\Routing;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class DefaultUriTest extends KernelTestCase
{
    public function testAbsoluteUrlsGeneratedOffRequestUseConfiguredDomain(): void
    {
        self::bootKernel();

        // The configured origin, as the stack provides it (compose.yaml derives DEFAULT_URI from
        // SERVER_NAME, so it differs per worktree - read it rather than hardcode a host).
        $configured = $_SERVER['DEFAULT_URI'] ?? $_ENV['DEFAULT_URI'] ?? getenv('DEFAULT_URI');
        self::assertIsString($configured);
        self::assertNotSame('', $configured);

        $router = self::getContainer()->get(RouterInterface::class);
        $url = $router->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);

        // With no HTTP request to populate the RequestContext (the worker/CLI case), the router falls
        // back to router.default_uri (= %env(DEFAULT_URI)%). Unwired, that default was http://localhost -
        // the bug this guards: magic-link and email-verification URLs are minted on the async worker.
        // Assert against the configured origin, not a fixed scheme: solo mode is plain http on loopback.
        self::assertStringStartsWith(rtrim($configured, '/') . '/', $url);
    }
}
