<?php

// ABOUTME: Feature tests for the login page - anonymous access, off-request magic-link dispatch, redirect guard.
// ABOUTME: A request queues RequestLoginLinkCommand on the async transport with no in-request email, for any address.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Auth;

use App\Factory\UserFactory;
use App\Message\Command\Auth\RequestLoginLinkCommand;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class LoginControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
    }

    public function testTheLoginPageRendersForAnonymousUsers(): void
    {
        $this->client->request(method: Request::METHOD_GET, uri: '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
        self::assertSelectorExists('input[name="login_request[email]"]');
    }

    public function testSubmittingAnEmailQueuesTheRequestOffRequestAndRedirects(): void
    {
        UserFactory::createOne(['email' => 'known@dev88.test']);

        $this->client->request(method: Request::METHOD_GET, uri: '/login');
        $this->client->submitForm('Email me a link', ['login_request[email]' => 'known@dev88.test']);

        self::assertResponseRedirects('/login');
        self::assertCount(1, $this->asyncTransport()->getSent());
        self::assertInstanceOf(RequestLoginLinkCommand::class, $this->asyncTransport()->getSent()[0]->getMessage());
        // The lookup + send happen on the worker, so nothing is emailed in-request.
        self::assertCount(0, $this->mailTransport()->getSent());
    }

    public function testTheResponseIsIdenticalForAnUnknownAccount(): void
    {
        $this->client->request(method: Request::METHOD_GET, uri: '/login');
        $this->client->submitForm('Email me a link', ['login_request[email]' => 'nobody@dev88.test']);

        // Same redirect and same queued command as a known address - the controller cannot tell them
        // apart, so the request path leaks nothing.
        self::assertResponseRedirects('/login');
        self::assertCount(1, $this->asyncTransport()->getSent());
    }

    public function testAnInvalidEmailReRendersTheForm(): void
    {
        $this->client->request(method: Request::METHOD_GET, uri: '/login');
        $this->client->submitForm('Email me a link', ['login_request[email]' => 'not-an-email']);

        self::assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->asyncTransport()->getSent());
    }

    public function testProtectedRoutesRedirectAnonymousUsersToLogin(): void
    {
        $this->client->request(method: Request::METHOD_GET, uri: '/');

        self::assertResponseRedirects('/login');
    }

    public function testAuthenticatedUsersReachTheApp(): void
    {
        $this->client->loginUser(UserFactory::founder());

        $this->client->request(method: Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
    }

    private function asyncTransport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }

    private function mailTransport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.mail');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }
}
