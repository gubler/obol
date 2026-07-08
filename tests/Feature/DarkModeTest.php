<?php

// ABOUTME: Feature tests that the dark-mode mechanism is wired into the page shell.
// ABOUTME: The toggle behavior itself is unit-tested in assets/controllers/theme_controller.test.js.

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Tests\Support\AuthenticatedTestCase;

final class DarkModeTest extends AuthenticatedTestCase
{
    public function testThemeToggleIsRenderedInTheNav(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/subscriptions/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'button[data-controller="theme"][data-action="theme#toggle"]');
        self::assertSelectorExists(selector: 'button[data-controller="theme"][aria-label]');
    }

    public function testNoFlashScriptResolvesTheThemeBeforePaint(): void
    {
        $client = $this->authenticatedClient();

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/subscriptions/new');

        self::assertResponseIsSuccessful();
        $head = $crawler->filter(selector: 'head')->html();
        self::assertStringContainsString('prefers-color-scheme: light', $head);
        self::assertStringContainsString("classList.toggle('dark'", $head);
    }
}
