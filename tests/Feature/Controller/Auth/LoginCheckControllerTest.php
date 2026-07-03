<?php

// ABOUTME: Feature tests for our firewall integration - a valid link logs in and sets remember-me,
// ABOUTME: and hitting the check route without a signature falls through our controller to the login form.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Auth;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

final class LoginCheckControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
    }

    public function testAFreshLinkAuthenticatesAndSetsARememberMeCookie(): void
    {
        $user = UserFactory::createOne(['email' => 'link@dev88.test']);

        /** @var LoginLinkHandlerInterface $loginLinkHandler */
        $loginLinkHandler = self::getContainer()->get('security.authenticator.login_link_handler.main');
        $url = $loginLinkHandler->createLoginLink($user)->getUrl();

        $this->client->request(method: Request::METHOD_GET, uri: $url);

        // A successful magic-link login redirects away from the login pages...
        self::assertResponseRedirects();
        self::assertNotNull($this->client->getCookieJar()->get('REMEMBERME'));

        // ...and the session is now authenticated: a protected page is reachable.
        $this->client->request(method: Request::METHOD_GET, uri: '/');
        self::assertResponseIsSuccessful();
    }

    public function testHittingCheckWithoutASignatureRedirectsToLogin(): void
    {
        $this->client->request(method: Request::METHOD_GET, uri: '/login/check');

        self::assertResponseRedirects('/login');
    }
}
