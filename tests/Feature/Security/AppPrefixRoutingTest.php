<?php

// ABOUTME: Firewall/routing tests for the /app surface boundary (ADR-0018).
// ABOUTME: /app is authenticated; / redirects to it; the email-verify link stays public outside /app.

declare(strict_types=1);

namespace App\Tests\Feature\Security;

use App\Tests\Support\AuthenticatedTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Ulid;

final class AppPrefixRoutingTest extends AuthenticatedTestCase
{
    public function testTheAppSurfaceRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request(method: Request::METHOD_GET, uri: '/app');

        self::assertResponseRedirects('/login');
    }

    public function testAuthenticatedUsersReachTheDashboardUnderApp(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Subscriptions');
    }

    public function testTheRootIsPublicAndOutsideTheAppWall(): void
    {
        // `/` is the public landing, not part of the ^/app surface: an anonymous visitor reaches it
        // with 200 rather than being bounced to /login.
        $client = self::createClient();

        $client->request(method: Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
    }

    public function testTheEmailVerificationLinkStaysPublicOutsideApp(): void
    {
        // An unsigned verify link is rejected with 403 - but it reaches the controller rather than being
        // bounced to /login, proving the endpoint is public and outside the ^/app firewall wall.
        $client = self::createClient();

        $client->request(method: Request::METHOD_GET, uri: '/account/emails/' . new Ulid() . '/verify');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
