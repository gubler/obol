<?php

// ABOUTME: Feature tests for TourController: the dedicated /app/tour view that stages a sample subscription.
// ABOUTME: Verifies the sample renders (tile + totals), the reassurance/exit chrome, and that nothing persists.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Tour;

use App\Tests\Support\AuthenticatedTestCase;
use Symfony\Component\HttpFoundation\Request;

final class TourControllerTest extends AuthenticatedTestCase
{
    public function testStagesASampleSubscriptionWithPopulatedTotals(): void
    {
        // The founder has no subscriptions, yet the tour view renders a full dashboard so every tour
        // step has a real target - a sample tile and a non-zero totals panel.
        $client = $this->authenticatedClient();

        $client->request(method: Request::METHOD_GET, uri: '/app/tour');

        self::assertResponseIsSuccessful();
        // The sample tile carries the tour hook so the walkthrough can highlight it and explain that a
        // subscription opens its detail page.
        self::assertSelectorExists(selector: '.subscription-tile[data-tour="tile"]');
        self::assertSelectorExists(selector: '.global-totals [data-tour="totals"], [data-tour="totals"]');
        self::assertSelectorTextContains(selector: '.global-total-monthly', text: '$');
    }

    public function testReassuresThatNothingWasAddedAndOffersAnExit(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: Request::METHOD_GET, uri: '/app/tour');

        self::assertResponseIsSuccessful();
        // A clear reassurance that the sample is not a real subscription, plus a way back out.
        self::assertSelectorExists(selector: '.tour-sample-notice');
        self::assertSelectorExists(selector: '.tour-sample-notice a[href="/app"]');
    }

    public function testAutostartsTheTourAndReturnsToTheDashboardOnExit(): void
    {
        $client = $this->authenticatedClient();

        $client->request(method: Request::METHOD_GET, uri: '/app/tour');

        self::assertResponseIsSuccessful();
        // The Stimulus controller is mounted to auto-drive the tour and to send the user home when it ends.
        self::assertSelectorExists(selector: '[data-controller~="tour"][data-tour-autostart-value="true"]');
        self::assertSelectorExists(selector: '[data-tour-return-url-value="/app"]');
    }

    public function testTheSampleNeverLeaksIntoTheRealDashboard(): void
    {
        // Visiting the tour must not persist anything: the real dashboard still shows the empty state.
        $client = $this->authenticatedClient();

        $client->request(method: Request::METHOD_GET, uri: '/app/tour');
        self::assertResponseIsSuccessful();

        $client->request(method: Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: '.empty-state');
        self::assertSelectorNotExists(selector: '.subscription-tile');
    }

    public function testRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request(method: Request::METHOD_GET, uri: '/app/tour');

        self::assertResponseRedirects();
    }
}
