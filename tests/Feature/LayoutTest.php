<?php

// ABOUTME: Feature tests for the base layout branding - the Obol coin favicon and header logo.
// ABOUTME: Guards that the placeholder Tailwind mark is gone and our icon is wired into every page.

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Tests\Support\AuthenticatedTestCase;

final class LayoutTest extends AuthenticatedTestCase
{
    public function testDeclaresSvgFavicon(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'head link[rel="icon"][type="image/svg+xml"]');
    }

    public function testDeclaresPngFaviconFallback(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'head link[rel="icon"][type="image/png"]');
    }

    public function testDeclaresAppleTouchIcon(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'head link[rel="apple-touch-icon"]');
    }

    public function testHeaderShowsObolLogo(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'nav img[alt="Obol"]');
    }

    public function testDropsPlaceholderCompanyMark(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(selector: 'img[alt="Your Company"]');
    }
}
