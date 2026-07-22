<?php

// ABOUTME: Feature tests for our magic-link firewall integration - a valid link renders a POST
// ABOUTME: interstitial (GET never consumes), submitting it logs in once, and a consumed link is dead.

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

    private function createLoginLinkUrl(string $email = 'link@dev88.test'): string
    {
        $user = UserFactory::createOne(['email' => $email]);

        /** @var LoginLinkHandlerInterface $loginLinkHandler */
        $loginLinkHandler = self::getContainer()->get('security.authenticator.login_link_handler.main');

        return $loginLinkHandler->createLoginLink($user)->getUrl();
    }

    public function testAValidLinkRendersTheSignInInterstitialWithoutAuthenticating(): void
    {
        $url = $this->createLoginLinkUrl();

        $crawler = $this->client->request(method: Request::METHOD_GET, uri: $url);

        // A GET (a human click, a mail-scanner prefetch, a browser preload) must NOT consume the link:
        // it renders an interstitial whose form POSTs back to complete login.
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('form[method="post"]')->count());
        self::assertNull($this->client->getCookieJar()->get('REMEMBERME'));

        // ...and nothing was authenticated: a protected page still bounces to login.
        $this->client->request(method: Request::METHOD_GET, uri: '/app');
        self::assertResponseRedirects();
    }

    public function testSubmittingTheInterstitialAuthenticatesAndSetsARememberMeCookie(): void
    {
        $url = $this->createLoginLinkUrl();

        $crawler = $this->client->request(method: Request::METHOD_GET, uri: $url);
        $this->client->submit($crawler->filter('form')->form());

        // The POST consumes the link and logs in...
        self::assertResponseRedirects();
        self::assertNotNull($this->client->getCookieJar()->get('REMEMBERME'));

        // ...and the session is now authenticated: a protected page is reachable.
        $this->client->request(method: Request::METHOD_GET, uri: '/app');
        self::assertResponseIsSuccessful();
    }

    public function testAConsumedLinkCannotBeReplayed(): void
    {
        $url = $this->createLoginLinkUrl();

        // First redemption: submit the interstitial, then drop the session so the replay starts clean.
        $crawler = $this->client->request(method: Request::METHOD_GET, uri: $url);
        $this->client->submit($crawler->filter('form')->form());
        $this->client->request(method: Request::METHOD_GET, uri: '/logout');

        // Replaying the same link: the interstitial still renders (GET is cheap), but the POST is rejected
        // because max_uses is exhausted and the redemption is recorded in the used-link cache.
        $crawler = $this->client->request(method: Request::METHOD_GET, uri: $url);
        $this->client->submit($crawler->filter('form')->form());

        $this->client->request(method: Request::METHOD_GET, uri: '/app');
        self::assertResponseRedirects();
    }

    public function testHittingCheckWithoutASignatureRedirectsToLogin(): void
    {
        $this->client->request(method: Request::METHOD_GET, uri: '/login/check');

        self::assertResponseRedirects('/login');
    }
}
