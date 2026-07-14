<?php

// ABOUTME: Feature tests for the admin user-management section - searchable/paginated list, detail, per-email resend.
// ABOUTME: The list is the app's first cross-owner read; everything sits behind the ROLE_ADMIN gate.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Admin;

use App\Factory\UserEmailFactory;
use App\Factory\UserFactory;
use App\Message\Command\Auth\RequestLoginLinkCommand;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Ulid;

final class UserManagementControllerTest extends WebTestCase
{
    public function testTheHubSidebarListsTheUsersSection(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app/admin/users');

        self::assertResponseIsSuccessful();
        $links = $crawler->filter('[data-test="admin-hub-nav-link"]')->each(
            static fn ($link): string => (string) $link->attr('href'),
        );
        self::assertContains('/app/admin/users', $links);
    }

    public function testListsEveryAccountAcrossOwners(): void
    {
        $client = self::createClient();
        UserFactory::createOne(['email' => 'alice@dev88.test']);
        UserFactory::createOne(['email' => 'bob@dev88.test']);
        $client->loginUser(UserFactory::founder());

        $emails = $this->rowEmails($client->request(Request::METHOD_GET, '/app/admin/users'));

        self::assertResponseIsSuccessful();
        // A deliberate cross-owner read: both accounts show, not just the signed-in operator's.
        self::assertContains('alice@dev88.test', $emails);
        self::assertContains('bob@dev88.test', $emails);
    }

    public function testSearchFiltersByDisplayName(): void
    {
        $client = self::createClient();
        // displayName is private(set), so it is set through the domain method, not the factory.
        $zephyr = UserFactory::createOne(['email' => 'zephyr@dev88.test']);
        $zephyr->changeDisplayName('Zephyr Quokka');
        UserFactory::createOne(['email' => 'alice@dev88.test']);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $client->loginUser(UserFactory::founder());

        $emails = $this->rowEmails($client->request(Request::METHOD_GET, '/app/admin/users?q=quokka'));

        self::assertResponseIsSuccessful();
        self::assertContains('zephyr@dev88.test', $emails);
        self::assertNotContains('alice@dev88.test', $emails);
    }

    public function testSearchMatchesAnyOfAUsersEmailAddresses(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        UserEmailFactory::createOne([
            'user' => $user,
            'email' => 'secondary-needle@dev88.test',
            'verifiedAt' => new \DateTimeImmutable(),
        ]);
        $client->loginUser(UserFactory::founder());

        // Searching a secondary address finds the account, even though its primary differs.
        $emails = $this->rowEmails($client->request(Request::METHOD_GET, '/app/admin/users?q=secondary-needle'));

        self::assertResponseIsSuccessful();
        self::assertContains('primary@dev88.test', $emails);
    }

    public function testSearchWithNoMatchesShowsTheEmptyState(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::founder());

        $client->request(Request::METHOD_GET, '/app/admin/users?q=no-such-account-xyz');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="admin-users-empty"]');
    }

    public function testTheListIsPaginated(): void
    {
        $client = self::createClient();
        // More than one page (per-page is 20) once the seeded and operator accounts are included.
        UserFactory::createMany(20);
        $client->loginUser(UserFactory::founder());

        $firstPage = $client->request(Request::METHOD_GET, '/app/admin/users');
        self::assertResponseIsSuccessful();
        self::assertCount(20, $firstPage->filter('[data-test="admin-user-row"]'));
        self::assertCount(1, $firstPage->filter('[data-test="admin-users-next"]'));

        $secondPage = $client->request(Request::METHOD_GET, '/app/admin/users?page=2');
        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $secondPage->filter('[data-test="admin-user-row"]')->count());
        self::assertCount(1, $secondPage->filter('[data-test="admin-users-prev"]'));
    }

    public function testUserDetailShowsReadOnlyAccountFields(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne([
            'email' => 'tester@dev88.test',
            'roles' => ['ROLE_ADMIN'],
        ]);
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app/admin/users/' . $user->id);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('tester@dev88.test', $crawler->filter('[data-test="admin-user-email"]')->text());
        self::assertStringContainsString('ROLE_ADMIN', $crawler->filter('[data-test="admin-user-roles"]')->text());
        self::assertCount(1, $crawler->filter('[data-test="admin-user-created"]'));
        self::assertCount(1, $crawler->filter('[data-test="admin-user-onboarding"]'));
        // Read-only: nothing on the page is an editable field (the only inputs are hidden resend targets).
        self::assertCount(0, $crawler->filter('input[type="text"], select, textarea'));
    }

    public function testResendLoginLinkToThePrimaryQueuesTheCommand(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'tester@dev88.test']);
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app/admin/users/' . $user->id);
        $client->submit($crawler->filter('[data-test-resend-email="tester@dev88.test"]')->form());

        self::assertResponseRedirects('/app/admin/users/' . $user->id);
        // Assert on the transport before following the redirect: the in-memory transport resets per request.
        self::assertSame('tester@dev88.test', $this->onlyQueuedLoginLink()->email);

        $client->followRedirect();
        self::assertSelectorExists('.flash-success');
    }

    public function testResendLoginLinkCanTargetAVerifiedSecondaryEmail(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        UserEmailFactory::createOne([
            'user' => $user,
            'email' => 'secondary@dev88.test',
            'verifiedAt' => new \DateTimeImmutable(),
        ]);
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app/admin/users/' . $user->id);
        $client->submit($crawler->filter('[data-test-resend-email="secondary@dev88.test"]')->form());

        self::assertResponseRedirects('/app/admin/users/' . $user->id);
        // The link goes to the address the operator picked, not the primary.
        self::assertSame('secondary@dev88.test', $this->onlyQueuedLoginLink()->email);
    }

    public function testResendRejectsAnEmailThatIsNotTheUsers(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'tester@dev88.test']);
        $client->loginUser(UserFactory::founder());

        $client->request(
            Request::METHOD_POST,
            '/app/admin/users/' . $user->id . '/resend-login-link',
            ['email' => 'attacker@evil.test'],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertCount(0, $this->asyncTransport()->getSent());
    }

    public function testAnUnknownUserDetailIs404(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::founder());

        $client->request(Request::METHOD_GET, '/app/admin/users/' . new Ulid());

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testARegularUserIsForbidden(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request(Request::METHOD_GET, '/app/admin/users');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * @return list<string>
     */
    private function rowEmails(\Symfony\Component\DomCrawler\Crawler $crawler): array
    {
        return $crawler->filter('[data-test="admin-user-row"]')->each(
            static fn ($row): string => (string) $row->attr('data-test-user-email'),
        );
    }

    private function onlyQueuedLoginLink(): RequestLoginLinkCommand
    {
        $sent = $this->asyncTransport()->getSent();
        self::assertCount(1, $sent);
        $message = $sent[0]->getMessage();
        self::assertInstanceOf(RequestLoginLinkCommand::class, $message);

        return $message;
    }

    private function asyncTransport(): InMemoryTransport
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }
}
