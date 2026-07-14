<?php

// ABOUTME: Feature tests for the admin invite-user flow - create an account and email it a login link.
// ABOUTME: A thin invite (no Invite entity); duplicate emails are rejected. Behind the ROLE_ADMIN gate.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Admin;

use App\Factory\UserEmailFactory;
use App\Factory\UserFactory;
use App\Message\Command\Auth\RequestLoginLinkCommand;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class InviteUserControllerTest extends WebTestCase
{
    public function testTheInviteFormIsReachableByAnAdmin(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app/admin/users/invite');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test="admin-invite-form"]'));
    }

    public function testTheUserListLinksToTheInviteForm(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app/admin/users');

        self::assertResponseIsSuccessful();
        self::assertSame('/app/admin/users/invite', $crawler->filter('[data-test="admin-invite-link"]')->attr('href'));
    }

    public function testInvitingANewEmailCreatesTheAccountAndQueuesALoginLink(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app/admin/users/invite');
        $form = $crawler->filter('[data-test="admin-invite-form"]')->form();
        $form['invite_user[email]'] = 'newbie@dev88.test';
        $client->submit($form);

        self::assertResponseRedirects('/app/admin/users');
        // A login link is queued for the invitee (async transport; assert before the redirect resets it).
        $sent = $this->asyncTransport()->getSent();
        self::assertCount(1, $sent);
        $message = $sent[0]->getMessage();
        self::assertInstanceOf(RequestLoginLinkCommand::class, $message);
        self::assertSame('newbie@dev88.test', $message->email);

        // The account exists with a verified primary email, so it can log in immediately.
        $user = self::getContainer()->get(UserRepository::class)->findForEmail('newbie@dev88.test');
        self::assertNotNull($user);

        $crawler = $client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertStringContainsString('newbie@dev88.test', $crawler->text());
    }

    public function testInvitingAnExistingEmailIsRejectedAndCreatesNothing(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'taken@dev88.test']);
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app/admin/users/invite');
        $form = $crawler->filter('[data-test="admin-invite-form"]')->form();
        $form['invite_user[email]'] = 'taken@dev88.test';
        $client->submit($form);

        // No redirect: the form re-renders with an error and nothing is queued.
        self::assertSelectorTextContains('[data-test="admin-invite-form"]', 'already');
        self::assertCount(0, $this->asyncTransport()->getSent());
    }

    public function testInvitingAVerifiedSecondaryOfAnotherUserIsRejected(): void
    {
        $client = self::createClient();
        $owner = UserFactory::createOne(['email' => 'owner@dev88.test']);
        UserEmailFactory::createOne([
            'user' => $owner,
            'email' => 'shared@dev88.test',
            'verifiedAt' => new \DateTimeImmutable(),
        ]);
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app/admin/users/invite');
        $form = $crawler->filter('[data-test="admin-invite-form"]')->form();
        $form['invite_user[email]'] = 'shared@dev88.test';
        $client->submit($form);

        self::assertSelectorTextContains('[data-test="admin-invite-form"]', 'already');
        self::assertCount(0, $this->asyncTransport()->getSent());
    }

    public function testARegularUserIsForbidden(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request(Request::METHOD_GET, '/app/admin/users/invite');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function asyncTransport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }
}
