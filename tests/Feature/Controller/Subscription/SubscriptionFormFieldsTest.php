<?php

// ABOUTME: Feature tests for the merged subscription form fields: the inline cycle control and the
// ABOUTME: inline cost/currency control (currency dropdown leading the amount, shown as symbol + ISO code).

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Enum\Currency;
use App\Factory\SubscriptionFactory;
use App\Tests\Support\AuthenticatedTestCase;
use App\ValueObject\Money;

final class SubscriptionFormFieldsTest extends AuthenticatedTestCase
{
    public function testCurrencyDropdownShowsEachCurrencySymbolAndIsoCode(): void
    {
        $client = $this->authenticatedClient();

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/subscriptions/new');

        self::assertResponseIsSuccessful();

        $usd = $crawler->filter('select[name="create_subscription[currency]"] option[value="USD"]');
        self::assertCount(1, $usd);
        self::assertStringContainsString('$', $usd->text());
        self::assertStringContainsString('USD', $usd->text());
    }

    public function testRendersTheCycleAsAnInlineEveryNPeriodControl(): void
    {
        $client = $this->authenticatedClient();

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/subscriptions/new');

        self::assertResponseIsSuccessful();

        $cycle = $crawler->filter('[data-controller="billing-cycle"]');
        self::assertCount(1, $cycle);
        self::assertStringContainsString('Every', $cycle->text());
        self::assertCount(1, $cycle->filter('input[name="create_subscription[paymentPeriodCount]"][data-billing-cycle-target="count"]'));
        $period = $cycle->filter('select[name="create_subscription[paymentPeriod]"][data-billing-cycle-target="period"]');
        self::assertCount(1, $period);
        // Each period option carries the singular noun the controller pluralizes against the count.
        self::assertSame('Year', $period->filter('option[value="year"]')->attr('data-singular'));
    }

    public function testRendersCostWithTheCurrencyDropdownLeadingTheAmount(): void
    {
        $client = $this->authenticatedClient();

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/subscriptions/new');

        self::assertResponseIsSuccessful();

        $group = $crawler->filter('.cost-field');
        self::assertCount(1, $group);
        self::assertCount(1, $group->filter('select[name="create_subscription[currency]"]'));
        self::assertCount(1, $group->filter('input[name="create_subscription[cost]"]'));

        // The currency dropdown precedes the amount input within the group.
        $html = $group->html();
        self::assertLessThan(
            strpos($html, 'name="create_subscription[cost]"'),
            strpos($html, 'name="create_subscription[currency]"'),
        );
    }

    public function testTheCurrencyDropdownDefaultsToTheSelectedCurrency(): void
    {
        $client = $this->authenticatedClient();
        $subscription = SubscriptionFactory::createOne([
            'name' => 'Manga Box',
            'cost' => new Money(1500, Currency::EUR),
        ]);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/subscriptions/' . $subscription->id . '/edit');

        self::assertResponseIsSuccessful();
        $selected = $crawler->filter('select[name="edit_subscription[currency]"] option[selected]');
        self::assertSame('EUR', $selected->attr('value'));
        self::assertStringContainsString('€', $selected->text());
    }
}
