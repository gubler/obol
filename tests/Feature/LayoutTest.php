<?php

// ABOUTME: Feature tests for the base layout branding - the Obol coin favicon and header logo.
// ABOUTME: Guards that the placeholder Tailwind mark is gone and our icon is wired into every page.

declare(strict_types=1);

namespace App\Tests\Feature;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LayoutTest extends WebTestCase
{
    public function testDeclaresSvgFavicon(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'head link[rel="icon"][type="image/svg+xml"]');
    }

    public function testHeaderShowsObolLogo(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'nav img[alt="Obol"]');
    }

    public function testDropsPlaceholderCompanyMark(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(selector: 'img[alt="Your Company"]');
    }
}
