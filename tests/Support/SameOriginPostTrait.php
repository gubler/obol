<?php

// ABOUTME: Test helper for POSTing to a CSRF-protected route the way a same-origin browser does.
// ABOUTME: Carries the stateless CSRF sentinel plus Sec-Fetch-Site so #[IsCsrfTokenValid] passes.

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

trait SameOriginPostTrait
{
    /**
     * POST as a same-origin browser would. The app's same-origin CSRF protection accepts a request that
     * either carries a same-origin Origin/Sec-Fetch-Site header or double-submits the token; a real form
     * submit does both, so this mirrors that (the rendered form's hidden `_token` plus the browser's
     * Sec-Fetch-Site header). Use it wherever a test must reach a protected controller body directly
     * rather than through a crawler-submitted form.
     *
     * @param array<string, mixed> $parameters
     */
    protected function postSameOrigin(KernelBrowser $client, string $uri, array $parameters = []): Crawler
    {
        /** @var CsrfTokenManagerInterface $csrfTokenManager */
        $csrfTokenManager = static::getContainer()->get('security.csrf.token_manager');
        $token = $csrfTokenManager->getToken('submit')->getValue();

        return $client->request(
            method: Request::METHOD_POST,
            uri: $uri,
            parameters: ['_token' => $token] + $parameters,
            server: ['HTTP_SEC_FETCH_SITE' => 'same-origin'],
        );
    }
}
