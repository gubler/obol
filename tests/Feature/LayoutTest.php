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

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'head link[rel="icon"][type="image/svg+xml"]');
    }

    public function testDeclaresPngFaviconFallback(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'head link[rel="icon"][type="image/png"]');
    }

    public function testDeclaresAppleTouchIcon(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'head link[rel="apple-touch-icon"]');
    }

    public function testDeclaresWebManifest(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'head link[rel="manifest"]');
    }

    public function testDeclaresThemeColor(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'head meta[name="theme-color"]');
    }

    public function testDeclaresStandaloneWebAppCapability(): void
    {
        // The installed app launches full-screen; assert both the standard and Apple capability metas.
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'head meta[name="mobile-web-app-capable"][content="yes"]');
        self::assertSelectorExists(selector: 'head meta[name="apple-mobile-web-app-capable"][content="yes"]');
    }

    public function testRendersTheHeaderBandOnPagesThatProvideOne(): void
    {
        // The dashboard fills the header block (title + New subscription), so the colored band renders.
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'header');
    }

    public function testOmitsTheHeaderBandWhenThePageHasNoHeader(): void
    {
        // Pages without a header block (e.g. Categories) must not render an empty colored band -
        // that leaves a slab of dead space above the content on mobile.
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/categories');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(selector: 'header');
    }

    public function testHeaderShowsObolLogo(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'nav img[alt="Obol"]');
    }

    public function testDropsPlaceholderCompanyMark(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(selector: 'img[alt="Your Company"]');
    }

    public function testFooterOffersTheTourOnTheDashboard(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'footer [data-action="tour#start"]');
    }

    public function testFooterDoesNotOfferTheTourOffTheDashboard(): void
    {
        // The tour highlights the dashboard, so the re-summon link only appears there - elsewhere it
        // would either hook nothing or force a navigation away.
        $client = $this->authenticatedClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app/categories');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(selector: 'footer [data-action="tour#start"]');
    }
}
