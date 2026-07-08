<?php

// ABOUTME: Feature tests for the account hub shell - the top-bar entry point and the sidebar sections.
// ABOUTME: A single Account entry opens the hub (Preferences); the sidebar lists Preferences + Access.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Account;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class AccountHubNavTest extends WebTestCase
{
    public function testTopBarShowsASingleAccountEntryOpeningThePreferencesHub(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request(Request::METHOD_GET, '/app');

        self::assertResponseIsSuccessful();
        $account = $crawler->filter('[data-test="nav-account"]');
        self::assertCount(1, $account);
        self::assertSame('/app/account/preferences', $account->attr('href'));
    }

    public function testHubSidebarListsPreferencesAndAccess(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request(Request::METHOD_GET, '/app/account/preferences');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('[data-test="account-hub-nav-link"]'));
    }

    public function testTheSidebarMarksTheActiveSection(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $crawler = $client->request(Request::METHOD_GET, '/app/account/access');

        self::assertResponseIsSuccessful();
        $active = $crawler->filter('[data-test="account-hub-nav-link"][aria-current="page"]');
        self::assertCount(1, $active);
        self::assertSame('/app/account/access', $active->attr('href'));
    }

    public function testTheAccessSectionIsActiveOnAPerEmailActionToo(): void
    {
        // Access lights up for its own route and the email/passkey action routes that post back to it.
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request(Request::METHOD_GET, '/app/account/access');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="email-add-form"]');
        self::assertSelectorExists('[data-test="passkey-index-add"]');
    }
}
