<?php

// ABOUTME: Feature tests for the onboarding gate - un-onboarded users are routed to /app/onboarding.
// ABOUTME: Covers the redirect, the onboarded pass-through, and the allowlist that avoids a redirect loop.

declare(strict_types=1);

namespace App\Tests\Feature\Security;

use App\Factory\UserFactory;
use App\Tests\Support\AuthenticatedTestCase;
use Symfony\Component\HttpFoundation\Request;

final class OnboardingGateListenerTest extends AuthenticatedTestCase
{
    public function testAnUnOnboardedUserIsRedirectedToOnboarding(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->notOnboarded()->create());

        $client->request(method: Request::METHOD_GET, uri: '/app');

        self::assertResponseRedirects('/app/onboarding');
    }

    public function testAnOnboardedUserReachesTheAppDirectly(): void
    {
        // The founder is onboarded by default; the gate lets them straight through.
        $client = $this->authenticatedClient();

        $client->request(method: Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
    }

    public function testTheOnboardingRouteItselfIsNotGatedSoThereIsNoLoop(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->notOnboarded()->create());

        $client->request(method: Request::METHOD_GET, uri: '/app/onboarding');

        self::assertResponseIsSuccessful();
    }

    public function testLogoutIsNotDivertedToOnboarding(): void
    {
        $client = self::createClient();
        $client->loginUser(UserFactory::new()->notOnboarded()->create());

        $client->request(method: Request::METHOD_GET, uri: '/logout');

        self::assertResponseRedirects();
        self::assertNotSame('/app/onboarding', $client->getResponse()->headers->get('Location'));
    }
}
