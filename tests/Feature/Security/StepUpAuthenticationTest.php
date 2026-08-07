<?php

// ABOUTME: Feature tests for the step-up gate - a remember-me-restored session cannot manage credentials
// ABOUTME: or reach the admin surface, is sent to re-prove identity, and lands back where it was headed.

declare(strict_types=1);

namespace App\Tests\Feature\Security;

use App\Entity\User;
use App\Factory\UserEmailFactory;
use App\Factory\UserFactory;
use App\Repository\UserRepository;
use App\Tests\Support\SameOriginPostTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

final class StepUpAuthenticationTest extends WebTestCase
{
    use SameOriginPostTrait;

    private int $linksMinted = 0;

    /**
     * A client whose only credential is the remember-me cookie - the downgrade this gate exists for.
     *
     * Built out of the real flow rather than by seeding a token, so it exercises `always_remember_me`
     * and the cookie's signature for real: log in properly, then throw away the session cookie and keep
     * only REMEMBERME. Every later request restores a RememberMeToken, which is not fully authenticated.
     */
    private function rememberedClient(KernelBrowser $client, User $user): void
    {
        $this->signInFully($client, $user);

        $cookieJar = $client->getCookieJar();
        $rememberMe = $cookieJar->get('REMEMBERME');
        self::assertNotNull($rememberMe, 'Logging in should issue a remember-me cookie.');

        $cookieJar->clear();
        $cookieJar->set($rememberMe);
    }

    /**
     * Redeem a fresh magic link on this client - a real, fully authenticated login.
     *
     * The lifetime is nudged on every call because the signature covers the expiry timestamp: two links
     * minted for the same user in the same second are byte-identical, and `max_uses: 1` would reject the
     * second as a replay of the first. Signing in twice in one test is exactly what the step-up needs.
     */
    private function signInFully(KernelBrowser $client, User $user): void
    {
        /** @var LoginLinkHandlerInterface $loginLinkHandler */
        $loginLinkHandler = self::getContainer()->get('security.authenticator.login_link_handler.main');
        $url = $loginLinkHandler->createLoginLink($user, lifetime: 900 + ++$this->linksMinted)->getUrl();

        $crawler = $client->request(Request::METHOD_GET, $url);
        $client->submit($crawler->filter('form')->form());
    }

    public function testARememberedAdminIsSentToReProveBeforeTheAdminSurface(): void
    {
        $client = self::createClient();
        $this->rememberedClient($client, UserFactory::founder());

        $client->request(Request::METHOD_GET, '/app/admin');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testAFullyAuthenticatedAdminReachesTheAdminSurface(): void
    {
        $client = self::createClient();
        $this->signInFully($client, UserFactory::founder());

        $client->request(Request::METHOD_GET, '/app/admin');

        self::assertResponseIsSuccessful();
    }

    public function testARememberedUserCanReachTheLoginPageToReProve(): void
    {
        $client = self::createClient();
        $this->rememberedClient($client, UserFactory::createOne());

        $client->request(Request::METHOD_GET, '/login');

        // The login page bounces users who are already signed in. If it bounced a remembered one too,
        // the step-up would have nowhere to go and the gate would be a dead end.
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="login_request[email]"]');
    }

    public function testARememberedSessionCannotPromoteAnAddress(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        $secondary = UserEmailFactory::createOne([
            'user' => $user,
            'email' => 'secondary@dev88.test',
            'verifiedAt' => new \DateTimeImmutable(),
        ]);
        $this->rememberedClient($client, $user);

        $this->postSameOrigin($client, '/app/account/emails/' . $secondary->id . '/promote');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));

        // The refusal has to be the point, not the redirect: the address is still not the primary.
        $users = self::getContainer()->get(UserRepository::class);
        self::assertSame('primary@dev88.test', $users->getForId($user->id)->email);
    }

    public function testARememberedSessionCannotAddAPasskey(): void
    {
        $client = self::createClient();
        $this->rememberedClient($client, UserFactory::createOne());

        $client->request(Request::METHOD_GET, '/app/account/passkeys/new');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    /**
     * The WebAuthn bundle owns the registration ceremony and there is no app controller to annotate,
     * so the firewall prefix is the only thing standing in front of it.
     */
    public function testARememberedSessionCannotOpenThePasskeyRegistrationCeremony(): void
    {
        $client = self::createClient();
        $this->rememberedClient($client, UserFactory::createOne());

        $this->postSameOrigin($client, '/app/account/passkeys/options');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testARememberedSessionStillReadsItsOwnAccount(): void
    {
        $client = self::createClient();
        $this->rememberedClient($client, UserFactory::createOne());

        // Reading discloses nothing a cookie holder cannot already see, so it must not demand a round
        // trip through the mailbox for ordinary navigation.
        $client->request(Request::METHOD_GET, '/app/account/access');
        self::assertResponseIsSuccessful();

        $client->request(Request::METHOD_GET, '/app/account/preferences');
        self::assertResponseIsSuccessful();
    }

    public function testTheAccessPageOffersAWayBackToFullAuthentication(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        UserEmailFactory::createOne(['user' => $user, 'email' => 'secondary@dev88.test', 'verifiedAt' => new \DateTimeImmutable()]);
        $this->rememberedClient($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/app/account/access');

        // The forms would 302 to /login on submit, so they are replaced by the prompt that gets there
        // deliberately - and, being a GET, comes back to this page afterwards.
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test="access-step-up"]'));
        self::assertCount(0, $crawler->filter('[data-test="email-add-form"]'));
        self::assertCount(0, $crawler->filter('[data-test="email-promote-form"]'));
        self::assertCount(0, $crawler->filter('[data-test="email-remove-form"]'));
        self::assertCount(0, $crawler->filter('[data-test="passkey-index-add"]'));
    }

    public function testAFullyAuthenticatedUserSeesTheFormsAndNoPrompt(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        UserEmailFactory::createOne(['user' => $user, 'email' => 'secondary@dev88.test', 'verifiedAt' => new \DateTimeImmutable()]);
        $this->signInFully($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/app/account/access');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[data-test="access-step-up"]'));
        self::assertCount(1, $crawler->filter('[data-test="email-add-form"]'));
        self::assertCount(1, $crawler->filter('[data-test="email-remove-form"]'));
        self::assertCount(1, $crawler->filter('[data-test="passkey-index-add"]'));
    }

    public function testTheStepUpPromptRoundTripsBackToTheAccessPage(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne();
        $this->rememberedClient($client, $user);

        $client->request(Request::METHOD_GET, '/app/account/access/confirm');
        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));

        $this->signInFully($client, $user);
        self::assertResponseRedirects();
        self::assertStringEndsWith('/app/account/access/confirm', (string) $client->getResponse()->headers->get('Location'));

        // Now fully authenticated, the confirm route is just a hop back to the hub.
        $client->followRedirect();
        self::assertResponseRedirects('/app/account/access');
    }

    public function testReProvingReturnsToTheRouteThatDemandedIt(): void
    {
        $client = self::createClient();
        $admin = UserFactory::founder();
        $this->rememberedClient($client, $admin);

        $client->request(Request::METHOD_GET, '/app/admin');
        self::assertResponseRedirects();

        $this->signInFully($client, $admin);

        // Symfony records the target path as the absolute request URI, so match the path, not the header.
        self::assertResponseRedirects();
        self::assertStringEndsWith('/app/admin', (string) $client->getResponse()->headers->get('Location'));
    }
}
