<?php

// ABOUTME: Locks in CSRF protection for the hand-built one-click POST actions across the app.
// ABOUTME: Without same-origin proof each is refused before its body runs; with it, the body is reached.

declare(strict_types=1);

namespace App\Tests\Feature\Security;

use App\Tests\Support\AuthenticatedTestCase;
use App\Tests\Support\SameOriginPostTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;

final class CsrfProtectionTest extends AuthenticatedTestCase
{
    use SameOriginPostTrait;

    #[DataProvider('protectedRoutes')]
    public function testPostWithoutSameOriginProofIsRefused(string $url): void
    {
        $client = $this->authenticatedClient();

        // A cross-site POST carries neither the CSRF token nor a same-origin Origin/Referer, so the
        // authenticated user is bounced to the login entry point instead of the action running.
        $client->request(Request::METHOD_POST, $url);

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    #[DataProvider('protectedRoutes')]
    public function testPostWithSameOriginProofReachesTheController(string $url): void
    {
        $client = $this->authenticatedClient();

        // Same request as above but same-origin (as a real form submit is): CSRF passes and the body
        // runs, 404-ing on the unknown id. Proves the guard - not the missing resource - blocks case one.
        $this->postSameOrigin($client, $url);

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Every state-changing hand-built POST form in the app, keyed by a readable name. The id is a
     * syntactically valid but non-existent ULID: CSRF is enforced ahead of the owner-scoped lookup,
     * so no fixture is needed to exercise the guard.
     *
     * @return iterable<string, array{string}>
     */
    public static function protectedRoutes(): iterable
    {
        $id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

        yield 'subscription delete' => ['/app/subscriptions/' . $id . '/delete'];
        yield 'subscription archive' => ['/app/subscriptions/' . $id . '/archive'];
        yield 'subscription unarchive' => ['/app/subscriptions/' . $id . '/unarchive'];
        yield 'payment delete' => ['/app/payments/' . $id . '/delete'];
        yield 'payment validate' => ['/app/payments/' . $id . '/validate'];
        yield 'category delete' => ['/app/categories/' . $id . '/delete'];
        yield 'payment source delete' => ['/app/payment-sources/' . $id . '/delete'];
        yield 'passkey revoke' => ['/app/account/passkeys/' . $id . '/delete'];
        yield 'email remove' => ['/app/account/emails/' . $id . '/remove'];
        yield 'email promote' => ['/app/account/emails/' . $id . '/promote'];
        yield 'email resend' => ['/app/account/emails/' . $id . '/resend'];
        yield 'admin resend login link' => ['/app/admin/users/' . $id . '/resend-login-link'];
    }
}
