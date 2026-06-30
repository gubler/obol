<?php

// ABOUTME: Feature tests that the dark-mode mechanism is wired into the page shell.
// ABOUTME: The toggle behavior itself is unit-tested in assets/controllers/theme_controller.test.js.

declare(strict_types=1);

namespace App\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DarkModeTest extends WebTestCase
{
    public function testThemeToggleIsRenderedInTheNav(): void
    {
        $client = self::createClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/subscriptions/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'button[data-controller="theme"][data-action="theme#toggle"]');
        self::assertSelectorExists(selector: 'button[data-controller="theme"][aria-label]');
    }

    public function testNoFlashScriptResolvesTheThemeBeforePaint(): void
    {
        $client = self::createClient();

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/subscriptions/new');

        self::assertResponseIsSuccessful();
        $head = $crawler->filter(selector: 'head')->html();
        self::assertStringContainsString('prefers-color-scheme: light', $head);
        self::assertStringContainsString("classList.toggle('dark'", $head);
    }
}
