<?php

// ABOUTME: Feature tests for the admin System Toggles section - shows and flips the public-signup setting.
// ABOUTME: Covers the read (current state), the write (persists across reload), and the ROLE_ADMIN gate.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Admin;

use App\Factory\UserFactory;
use App\Lib\Bus\CommandBus;
use App\Message\Command\System\SetPublicSignupCommand;
use App\Repository\SystemSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SystemTogglesControllerTest extends WebTestCase
{
    public function testTheHubSidebarListsTheSystemTogglesSection(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app/admin/system-toggles');

        self::assertResponseIsSuccessful();
        $links = $crawler->filter('[data-test="admin-hub-nav-link"]')->each(
            static fn ($link): string => (string) $link->attr('href'),
        );
        self::assertContains('/app/admin/system-toggles', $links);
    }

    public function testShowsPublicSignupOffByDefault(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app/admin/system-toggles');

        self::assertResponseIsSuccessful();
        // The seeded default is off: the checkbox is present and unchecked.
        self::assertCount(1, $crawler->filter('[data-test="system-toggles-public-signup"]'));
        self::assertNull($crawler->filter('[data-test="system-toggles-public-signup"]')->attr('checked'));
    }

    public function testEnablingPublicSignupPersists(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app/admin/system-toggles');
        $form = $crawler->filter('[data-test="system-toggles-form"]')->form();
        $this->publicSignupCheckbox($form)->tick();
        $client->submit($form);

        self::assertResponseRedirects('/app/admin/system-toggles');
        $client->followRedirect();
        self::assertSelectorExists('.flash-success');
        self::assertTrue($this->currentPublicSignup());
    }

    public function testDisablingPublicSignupPersists(): void
    {
        $client = self::createClient();
        self::getContainer()->get(CommandBus::class)->dispatch(new SetPublicSignupCommand(enabled: true));
        $client->loginUser(UserFactory::founder());

        $crawler = $client->request(Request::METHOD_GET, '/app/admin/system-toggles');
        $form = $crawler->filter('[data-test="system-toggles-form"]')->form();
        $this->publicSignupCheckbox($form)->untick();
        $client->submit($form);

        self::assertResponseRedirects('/app/admin/system-toggles');
        self::assertFalse($this->currentPublicSignup());
    }

    public function testARegularUserIsForbidden(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne());

        $client->request(Request::METHOD_GET, '/app/admin/system-toggles');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function publicSignupCheckbox(Form $form): ChoiceFormField
    {
        $checkbox = $form['system_toggles[publicSignupEnabled]'];
        self::assertInstanceOf(ChoiceFormField::class, $checkbox);

        return $checkbox;
    }

    private function currentPublicSignup(): bool
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return self::getContainer()->get(SystemSettingsRepository::class)->get()->publicSignupEnabled;
    }
}
