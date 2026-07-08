<?php

// ABOUTME: Feature tests for the /app/account/emails management surface - the address list and the add-secondary form.
// ABOUTME: Per-row action tests (promote / remove / resend) live alongside as those slices land.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Account;

use App\Entity\UserEmail;
use App\Factory\UserEmailFactory;
use App\Factory\UserFactory;
use App\Repository\UserEmailRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class EmailManagementControllerTest extends WebTestCase
{
    public function testIndexListsTheAccountsAddressesWithBadges(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        UserEmailFactory::createOne(['user' => $user, 'email' => 'verified@dev88.test', 'verifiedAt' => new \DateTimeImmutable()]);
        UserEmailFactory::new()->unverified()->create(['user' => $user, 'email' => 'pending@dev88.test']);
        $client->loginUser($user);

        $crawler = $client->request(Request::METHOD_GET, '/app/account/access');

        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('[data-test="email-row"]'));
        self::assertCount(1, $crawler->filter('[data-test="email-badge-primary"]'));
        self::assertCount(1, $crawler->filter('[data-test="email-badge-verified"]'));
        self::assertCount(1, $crawler->filter('[data-test="email-badge-pending"]'));
    }

    public function testAddingASecondaryRedirectsWithAGenericNoticeAndMailsTheAddress(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        $client->loginUser($user);

        $crawler = $client->request(Request::METHOD_GET, '/app/account/access');
        $form = $crawler->filter('[data-test="email-add-form"]')->form();
        $form['add_secondary_email[email]'] = 'new@dev88.test';
        $client->submit($form);

        self::assertResponseRedirects('/app/account/access');
        $client->followRedirect();
        self::assertSelectorExists('.flash-notice');

        // The pending row is persisted; the verification email queued by the handler is covered in
        // AddSecondaryEmailHandlerTest (asserting it here would fight the follow-redirect kernel reboot).
        $userEmails = self::getContainer()->get(UserEmailRepository::class);
        $row = $userEmails->findForUserByEmail($user, 'new@dev88.test');
        self::assertInstanceOf(UserEmail::class, $row);
        self::assertFalse($row->isVerified());
    }

    public function testAddingAnInvalidAddressFlashesAnErrorAndAddsNothing(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        $client->loginUser($user);

        $crawler = $client->request(Request::METHOD_GET, '/app/account/access');
        $form = $crawler->filter('[data-test="email-add-form"]')->form();
        $form['add_secondary_email[email]'] = 'not-an-email';
        $client->submit($form);

        self::assertResponseRedirects('/app/account/access');
        $client->followRedirect();
        self::assertSelectorExists('.flash-error');
        // The keyed flash resolves to real copy, not a raw translation key.
        self::assertSelectorTextContains('.flash-error', 'valid email address');

        $userEmails = self::getContainer()->get(UserEmailRepository::class);
        self::assertCount(1, $userEmails->findForOwnerId($user->id)); // only the primary
    }

    public function testIndexRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/app/account/access');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }
}
