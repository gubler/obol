<?php

// ABOUTME: Feature tests for OnboardingController - the first-run screen that confirms name/currency/timezone.
// ABOUTME: Covers the guessed-currency pre-selection and that submitting persists the settings and offers the tour.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Onboarding;

use App\Entity\User;
use App\Enum\Currency;
use App\Factory\UserFactory;
use App\Tests\Support\AuthenticatedTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class OnboardingControllerTest extends AuthenticatedTestCase
{
    public function testShowsTheFormWithTheCurrencyGuessedFromTheBrowserLocale(): void
    {
        // Boot the client before building the user, so the kernel is booted exactly once.
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->notOnboarded()->create());

        $crawler = $client->request(
            method: Request::METHOD_GET,
            uri: '/onboarding',
            server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE,de;q=0.9'],
        );

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="onboarding[displayName]"]');
        self::assertSelectorExists('select[name="onboarding[timezone]"]');
        // The browser locale (Germany) pre-selects EUR, overridable.
        self::assertSame(
            'EUR',
            $crawler->filter('select[name="onboarding[displayCurrency]"] option[selected]')->attr('value'),
        );
    }

    public function testAnAlreadyOnboardedUserIsRedirectedAwayFromOnboarding(): void
    {
        // The founder is onboarded; hitting /onboarding again should bounce to the app rather than
        // re-render the form (and a re-submit would trip completeOnboarding's idempotency guard).
        $client = $this->authenticatedClient();

        $client->request(method: Request::METHOD_GET, uri: '/onboarding');

        self::assertResponseRedirects('/');
    }

    public function testCompletingOnboardingPersistsTheSettingsAndOffersTheTour(): void
    {
        $client = self::createClient();
        $user = UserFactory::new()->notOnboarded()->create();
        $client->loginUser($user);

        $crawler = $client->request(method: Request::METHOD_GET, uri: '/onboarding');
        $form = $crawler->selectButton('Get started')->form();
        $form['onboarding[displayName]'] = 'Magos';
        $form['onboarding[displayCurrency]'] = 'GBP';
        $form['onboarding[timezone]'] = 'Europe/London';

        $client->submit($form);

        // Lands on the dashboard with the tour offer flagged.
        self::assertResponseRedirects('/?welcome=1');

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->clear();
        $persisted = $entityManager->find(User::class, $user->id);

        self::assertInstanceOf(User::class, $persisted);
        self::assertTrue($persisted->hasCompletedOnboarding());
        self::assertSame('Magos', $persisted->displayName);
        self::assertSame(Currency::GBP, $persisted->displayCurrency);
        self::assertSame('Europe/London', $persisted->timezone);
    }
}
