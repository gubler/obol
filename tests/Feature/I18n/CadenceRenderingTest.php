<?php

// ABOUTME: Guards that the subscription list view renders the pluralized cadence through the ICU
// ABOUTME: message (the empty-DB tripwire never reaches a row, so this needs a real subscription).

declare(strict_types=1);

namespace App\Tests\Feature\I18n;

use App\Enum\PaymentPeriod;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\TranslationAssertions;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CadenceRenderingTest extends WebTestCase
{
    use TranslationAssertions;

    public function testListViewRendersThePluralizedCadence(): void
    {
        $client = self::createClient();
        SubscriptionFactory::createOne([
            'name' => 'Quarterly Co',
            'paymentPeriod' => PaymentPeriod::Month,
            'paymentPeriodCount' => 3,
        ]);

        $client->request(method: 'GET', uri: '/?view=list');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: '.subscription-list', text: '3 months');
        self::assertNoTranslationKeyLeaks((string) $client->getResponse()->getContent(), 'subscription list view');
    }
}
