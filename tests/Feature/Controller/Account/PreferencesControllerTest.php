<?php

// ABOUTME: Feature tests for the account hub's Preferences section - the read-only view and the edit form.
// ABOUTME: The view shows settings as text with an Edit link; the edit form saves name + formatting.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Account;

use App\Entity\User;
use App\Enum\Currency;
use App\Enum\DateFormat;
use App\Factory\UserFactory;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Ulid;

final class PreferencesControllerTest extends WebTestCase
{
    public function testTheViewShowsTheCurrentPreferencesAsTextWithAnEditLink(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne([
            'email' => 'magos@dev88.test',
            'locale' => 'en-GB',
            'displayCurrency' => Currency::GBP,
        ]);
        $client->loginUser($user);

        $crawler = $client->request(Request::METHOD_GET, '/app/account/preferences');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test="preferences-summary"]'));
        self::assertSame('magos@dev88.test', trim($crawler->filter('[data-test="pref-display-name"]')->text()));
        self::assertStringContainsString('GBP', $crawler->filter('[data-test="pref-currency"]')->text());
        self::assertStringContainsString('English (United Kingdom)', $crawler->filter('[data-test="pref-language"]')->text());
        // The Edit link points at the edit page; there is no editable form on the view itself.
        self::assertSame('/app/account/preferences/edit', $crawler->filter('[data-test="preferences-edit-link"]')->attr('href'));
        self::assertCount(0, $crawler->filter('[data-test="preferences-form"]'));
    }

    public function testTheEditFormIsPrefilledWithTheCurrentPreferences(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne([
            'email' => 'magos@dev88.test',
            'locale' => 'en-GB',
            'displayCurrency' => Currency::GBP,
        ]);
        $client->loginUser($user);

        $crawler = $client->request(Request::METHOD_GET, '/app/account/preferences/edit');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test="preferences-form"]'));
        self::assertSame('magos@dev88.test', $crawler->filter('[data-test="preferences-display-name"]')->attr('value'));
        self::assertSame('en-GB', $crawler->filter('[data-test="preferences-language"] option[selected]')->attr('value'));
        self::assertSame('GBP', $crawler->filter('[data-test="preferences-currency"] option[selected]')->attr('value'));
    }

    public function testSavingTheEditFormPersistsNameAndSettingsThenRedirectsToTheView(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne([
            'email' => 'magos@dev88.test',
            'locale' => 'en-US',
            'displayCurrency' => Currency::USD,
            'timezone' => 'America/New_York',
            'dateFormat' => DateFormat::Medium,
        ]);
        $client->loginUser($user);

        $crawler = $client->request(Request::METHOD_GET, '/app/account/preferences/edit');
        $form = $crawler->filter('[data-test="preferences-form"]')->form();
        $form['change_preferences[displayName]'] = 'Magos';
        $form['change_preferences[displayCurrency]'] = Currency::GBP->value;
        $form['change_preferences[language]'] = 'en-GB';
        $form['change_preferences[dateFormat]'] = DateFormat::Short->value;
        $form['change_preferences[timezone]'] = 'Europe/London';
        $client->submit($form);

        self::assertResponseRedirects('/app/account/preferences');
        $client->followRedirect();
        self::assertSelectorExists('.flash-success');

        $reloaded = $this->reloadUser($user->id);
        self::assertSame('Magos', $reloaded->displayName);
        self::assertSame(Currency::GBP, $reloaded->displayCurrency);
        self::assertSame('en-GB', $reloaded->locale);
        self::assertSame('Europe/London', $reloaded->timezone);
        self::assertSame(DateFormat::Short, $reloaded->dateFormat);
    }

    public function testEachDateTimeFormatOptionIsHumanLabelledWithALiveExample(): void
    {
        // The picker offers Long/Medium/Short/ISO, each labelled with the current datetime in that
        // style, so a user picks by what dates actually look like - no dev-speak like "Locale default".
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne(['locale' => 'en-US']));

        $crawler = $client->request(Request::METHOD_GET, '/app/account/preferences/edit');

        self::assertResponseIsSuccessful();
        $values = $crawler->filter('[data-test="preferences-date-format"] option')->each(
            static fn ($option): string => (string) $option->attr('value'),
        );
        self::assertSame(['long', 'medium', 'short', 'iso'], $values);

        foreach (['long', 'medium', 'short', 'iso'] as $style) {
            $label = trim($crawler->filter('[data-test="preferences-date-format"] option[value="' . $style . '"]')->text());
            self::assertMatchesRegularExpression('/\(.+\)$/', $label);
        }
    }

    public function testTheEditPickerShowsAPlaceholderWhenTheCurrentLocaleHasNoShippedCatalog(): void
    {
        // A browser-guessed region with no catalog (e.g. de-DE) has no picker case: no option is
        // pre-selected, so the placeholder stands and the user must pick a shipped language.
        $client = self::createClient();
        $client->loginUser(UserFactory::createOne(['locale' => 'de-DE']));

        $crawler = $client->request(Request::METHOD_GET, '/app/account/preferences/edit');

        self::assertResponseIsSuccessful();
        self::assertSame('', $crawler->filter('[data-test="preferences-language"] option[selected]')->attr('value'));
    }

    public function testTheViewRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/app/account/preferences');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testTheEditPageRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/app/account/preferences/edit');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    private function reloadUser(Ulid $id): User
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return self::getContainer()->get(UserRepository::class)->getForId($id);
    }
}
