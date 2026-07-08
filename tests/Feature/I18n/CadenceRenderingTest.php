<?php

// ABOUTME: Guards that the subscription list view renders the pluralized cadence through the ICU
// ABOUTME: message (the empty-DB tripwire never reaches a row, so this needs a real subscription).

declare(strict_types=1);

namespace App\Tests\Feature\I18n;

use App\Enum\PaymentPeriod;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use App\Tests\Support\TranslationAssertions;

final class CadenceRenderingTest extends AuthenticatedTestCase
{
    use TranslationAssertions;

    public function testListViewRendersThePluralizedCadence(): void
    {
        $client = $this->authenticatedClient();
        SubscriptionFactory::createOne([
            'name' => 'Quarterly Co',
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 3,
        ]);

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/app?view=list');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: '.subscription-list', text: '3 months');
        self::assertNoTranslationKeyLeaks((string) $client->getResponse()->getContent(), 'subscription list view');
    }
}
