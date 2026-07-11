<?php

// ABOUTME: Feature tests for the public landing page served at logged-out `/`.
// ABOUTME: Covers anonymous rendering, the login CTA, and the "sign up for updates" email capture.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Landing;

use App\Tests\Support\AuthenticatedTestCase;
use App\Tests\Support\TranslationAssertions;
use Symfony\Component\HttpFoundation\Request;

final class LandingControllerTest extends AuthenticatedTestCase
{
    use TranslationAssertions;

    public function testAnonymousHomeRendersTheLandingPage(): void
    {
        $client = self::createClient();

        $client->request(method: Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: '[data-test="landing"]');
    }

    public function testTheLandingOffersALoginCta(): void
    {
        $client = self::createClient();

        $client->request(method: Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: '[data-test="landing"] a[href="/login"]');
    }

    public function testTheLandingOffersAnUpdatesSignupForm(): void
    {
        $client = self::createClient();

        $client->request(method: Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'form[action="/updates"] input[type="email"]');
    }

    public function testTheLandingRendersNoUnresolvedTranslationKeys(): void
    {
        $client = self::createClient();

        $client->request(method: Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
        self::assertNoTranslationKeyLeaks((string) $client->getResponse()->getContent(), 'landing');
    }

    public function testSignedInVisitorsAlsoSeeTheLandingAtRoot(): void
    {
        // `/` is the public front door for everyone: a signed-in user can view the marketing site
        // (the dashboard lives at /app). This is the payoff of moving the app under /app (ADR-0018).
        $client = $this->authenticatedClient();

        $client->request(method: Request::METHOD_GET, uri: '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: '[data-test="landing"]');
    }

    public function testSubmittingAValidEmailRedirectsHomeWithAConfirmation(): void
    {
        $client = self::createClient();

        $client->request(method: Request::METHOD_GET, uri: '/');
        $client->submitForm(button: 'Notify me', fieldValues: ['updates_signup[email]' => 'curious@dev88.test']);

        self::assertResponseRedirects('/');

        $client->followRedirect();
        self::assertSelectorExists(selector: '.flash-success');
    }

    public function testAnInvalidEmailReRendersTheLandingWithoutRedirecting(): void
    {
        $client = self::createClient();

        $client->request(method: Request::METHOD_GET, uri: '/');
        $client->submitForm(button: 'Notify me', fieldValues: ['updates_signup[email]' => 'not-an-email']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists(selector: '[data-test="landing"]');
    }
}
