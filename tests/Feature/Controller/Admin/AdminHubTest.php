<?php

// ABOUTME: Feature tests for the admin area shell - the /app/admin hub, its ROLE_ADMIN gate, and the nav link.
// ABOUTME: Admins reach the hub and see the Admin nav entry; regular users are forbidden and never see it.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Admin;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminHubTest extends WebTestCase
{
    public function testAnAdminReachesTheHubShell(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app/admin');

        self::assertResponseIsSuccessful();
        // The sidebar is data-driven (like the account hub): at least the Overview entry is present,
        // and it points back at the hub root. Later slices append sections without touching this.
        $links = $crawler->filter('[data-test="admin-hub-nav-link"]');
        self::assertGreaterThanOrEqual(1, $links->count());
        self::assertSame('/app/admin', $links->first()->attr('href'));
    }

    public function testARegularUserIsForbidden(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request(Request::METHOD_GET, '/app/admin');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAnAnonymousVisitorIsRedirectedToLogin(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/app/admin');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testTheAdminNavLinkShowsForAnAdmin(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app');

        self::assertResponseIsSuccessful();
        $admin = $crawler->filter('[data-test="nav-admin"]');
        self::assertCount(1, $admin);
        self::assertSame('/app/admin', $admin->attr('href'));
    }

    public function testTheAdminNavLinkIsHiddenFromRegularUsers(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request(Request::METHOD_GET, '/app');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-test="nav-admin"]'));
    }
}
