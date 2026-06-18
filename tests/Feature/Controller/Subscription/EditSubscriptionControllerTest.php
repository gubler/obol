<?php

// ABOUTME: Feature tests for EditSubscriptionController verifying subscription editing functionality.
// ABOUTME: Tests ensure proper form rendering with existing data, validation, and successful updates.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Subscription;

use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\TileColor;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\Repository\SubscriptionRepository;
use App\Tests\Support\TranslationAssertions;
use App\ValueObject\Money;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EditSubscriptionControllerTest extends WebTestCase
{
    use TranslationAssertions;

    public function testGetRequestDisplaysEditFormWithExistingData(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Edit Subscription');
        self::assertSelectorExists(selector: 'form');
        self::assertSelectorExists(selector: 'input[name="edit_subscription[name]"][value="Netflix"]');
        // The edit page is not crawled by the i18n tripwire, so guard it here (ADR-0012).
        self::assertNoTranslationKeyLeaks((string) $client->getResponse()->getContent(), 'edit subscription page');
    }

    public function testCategoryDropdownIsOrderedAlphabetically(): void
    {
        $client = self::createClient();
        // Created out of alphabetical sequence so creation order != name order.
        CategoryFactory::createOne(['name' => 'Zoom']);
        $apple = CategoryFactory::createOne(['name' => 'Apple']);
        CategoryFactory::createOne(['name' => 'Microsoft']);
        $subscription = SubscriptionFactory::createOne(['category' => $apple]);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        self::assertResponseIsSuccessful();
        $options = $crawler
            ->filter('select[name="edit_subscription[category]"] option')
            ->each(static fn ($node): string => $node->text())
        ;

        // First option is the "Uncategorized" placeholder; the categories follow alphabetically.
        self::assertSame(['Uncategorized', 'Apple', 'Microsoft', 'Zoom'], $options);
    }

    public function testGetRequestWithInvalidIdReturns404(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/edit');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testEditFormDoesNotWireTheColorSyncController(): void
    {
        $client = self::createClient();
        CategoryFactory::createOne(['name' => 'Apple', 'color' => TileColor::Teal]);
        $subscription = SubscriptionFactory::createOne(['name' => 'Netflix']);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        self::assertResponseIsSuccessful();
        // Color-sync is a new-form convenience only; the edit form must stay unaffected.
        self::assertSelectorNotExists(selector: '[data-controller~="color-sync"]');
        self::assertSelectorNotExists(selector: '[data-color-sync-target]');
        self::assertSelectorNotExists(selector: 'option[data-color]');
    }

    public function testPostRequestWithValidDataUpdatesSubscription(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $newCategory = CategoryFactory::createOne(['name' => 'Utilities']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
        ]);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        $form = $crawler->selectButton(value: 'Save')->form([
            'edit_subscription[category]' => $newCategory->id->toBase32(),
            'edit_subscription[name]' => 'Netflix Premium',
            'edit_subscription[nextRenewal]' => '2026-02-01',
            'edit_subscription[paymentPeriod]' => 'year',
            'edit_subscription[paymentPeriodCount]' => '1',
            'edit_subscription[cost]' => '19.99',
            'edit_subscription[description]' => 'Updated description',
            'edit_subscription[link]' => 'https://netflix.com/premium',
            'edit_subscription[color]' => 'teal',
        ]);

        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/subscriptions/' . $subscription->id);

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        /** @var SubscriptionRepository $repository */
        $repository = $entityManager->getRepository(className: Subscription::class);
        $entityManager->clear();

        $subscription = $repository->find($subscription->id);
        self::assertNotNull($subscription);

        self::assertSame('Netflix Premium', $subscription->name);
        self::assertSame(1999, $subscription->cost->minorAmount);
        self::assertSame('Updated description', $subscription->description);
        self::assertSame('https://netflix.com/premium', $subscription->link);
        self::assertSame(TileColor::Teal, $subscription->color);
        self::assertTrue($newCategory->id->equals($subscription->category->id));
    }

    public function testClearingTheCategoryLeavesTheSubscriptionUncategorized(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
        ]);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        $form = $crawler->selectButton(value: 'Save')->form([
            'edit_subscription[name]' => 'Netflix',
            'edit_subscription[nextRenewal]' => '2026-02-01',
            'edit_subscription[paymentPeriod]' => 'year',
            'edit_subscription[paymentPeriodCount]' => '1',
            'edit_subscription[cost]' => '15.99',
            'edit_subscription[color]' => 'teal',
        ]);
        $form['edit_subscription[category]'] = '';

        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/subscriptions/' . $subscription->id);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);
        $entityManager->clear();

        $subscription = $entityManager->getRepository(Subscription::class)->find($subscription->id);
        self::assertNotNull($subscription);
        self::assertNull($subscription->category);
    }

    public function testChangesTheCurrencyWhileTheSubscriptionHasNoPayments(): void
    {
        $client = self::createClient();
        $subscription = SubscriptionFactory::createOne([
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
        ]);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');
        $form = $crawler->selectButton(value: 'Save')->form([
            'edit_subscription[currency]' => 'EUR',
        ]);
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/subscriptions/' . $subscription->id);

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $entityManager->clear();
        $updated = $entityManager->getRepository(Subscription::class)->find($subscription->id);

        self::assertSame(Currency::EUR, $updated->cost->currency);
    }

    public function testLocksTheCurrencyFieldOnceAPaymentHasBeenRecorded(): void
    {
        $client = self::createClient();
        $subscription = SubscriptionFactory::new()->withRecentPayment()->create([
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        self::assertResponseIsSuccessful();
        // The picker is rendered disabled, so the currency cannot be resubmitted once payments exist.
        self::assertSelectorExists(selector: 'select[name="edit_subscription[currency]"][disabled]');
    }

    public function testDoesNotOfferTheRestartControlForAnAutomatedSubscription(): void
    {
        $client = self::createClient();
        $subscription = SubscriptionFactory::createOne(['name' => 'Netflix']);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(selector: '#edit_subscription_restartPaymentGeneration');
    }

    public function testResumesAutomatedGenerationFromTheEditFormWhenRestartIsRequested(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::new()->manual()->create([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
        ]);

        $future = (new \DateTimeImmutable('+45 days'))->format('Y-m-d');

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        self::assertSelectorExists(selector: '#edit_subscription_restartPaymentGeneration');

        $form = $crawler->selectButton(value: 'Save')->form([
            'edit_subscription[category]' => $category->id->toBase32(),
            'edit_subscription[name]' => 'Netflix',
            'edit_subscription[nextRenewal]' => $future,
            'edit_subscription[paymentPeriod]' => 'month',
            'edit_subscription[paymentPeriodCount]' => '1',
            'edit_subscription[cost]' => '15.99',
            'edit_subscription[color]' => 'teal',
            'edit_subscription[restartPaymentGeneration]' => '1',
        ]);
        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/subscriptions/' . $subscription->id);

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $entityManager->clear();

        $updated = $entityManager->getRepository(className: Subscription::class)->find($subscription->id);
        self::assertTrue($updated->generatesPaymentsAutomatically());
        self::assertSame($future, $updated->nextRenewal->format('Y-m-d'));
    }

    public function testRejectsARestartWithANonFutureRenewalDate(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::new()->manual()->create([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
        ]);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        $form = $crawler->selectButton(value: 'Save')->form([
            'edit_subscription[category]' => $category->id->toBase32(),
            'edit_subscription[name]' => 'Netflix',
            'edit_subscription[nextRenewal]' => '2020-01-01',
            'edit_subscription[paymentPeriod]' => 'month',
            'edit_subscription[paymentPeriodCount]' => '1',
            'edit_subscription[cost]' => '15.99',
            'edit_subscription[color]' => 'teal',
            'edit_subscription[restartPaymentGeneration]' => '1',
        ]);
        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $entityManager->clear();

        // Still manual - the invalid restart did not take effect.
        $updated = $entityManager->getRepository(className: Subscription::class)->find($subscription->id);
        self::assertFalse($updated->generatesPaymentsAutomatically());
    }

    public function testPostRequestWithValidDataShowsSuccessFlashMessage(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Spotify',
        ]);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        $form = $crawler->selectButton(value: 'Save')->form([
            'edit_subscription[name]' => 'Spotify Premium',
        ]);

        $client->submit(form: $form);
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-success', text: 'Subscription updated successfully');
    }

    public function testPostRequestWithEmptyNameShowsValidationError(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['edit_subscription[name]'] = '';

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.text-danger');
        self::assertSelectorTextContains(selector: 'body', text: 'This value should not be blank');
    }

    public function testPostRequestWithInvalidIdReturns404(): void
    {
        $client = self::createClient();

        $client->request(method: 'POST', uri: '/subscriptions/01JKXXXXXXXXXXXXXXXXXXXXXXX/edit');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testFormIncludesCsrfProtection(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'input[name="edit_subscription[_token]"]');
    }

    public function testShowsCancelLinkBackToSubscription(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
        ]);

        $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/subscriptions/' . $subscription->id . '"]');
    }

    public function testUpdatesCreateSubscriptionEvents(): void
    {
        $client = self::createClient();
        $category = CategoryFactory::createOne(['name' => 'Entertainment']);
        $subscription = SubscriptionFactory::createOne([
            'category' => $category,
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
        ]);

        $initialEventCount = \count($subscription->subscriptionEvents);

        $crawler = $client->request(method: 'GET', uri: '/subscriptions/' . $subscription->id . '/edit');

        $form = $crawler->selectButton(value: 'Save')->form([
            'edit_subscription[name]' => 'Netflix Premium',
            'edit_subscription[cost]' => '19.99',
        ]);

        $client->submit(form: $form);

        $container = self::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        /** @var SubscriptionRepository $repository */
        $repository = $entityManager->getRepository(className: Subscription::class);
        $entityManager->clear();

        $subscription = $repository->find($subscription->id);
        self::assertNotNull($subscription);

        // Should have at least one new event (Update and/or CostChange)
        self::assertGreaterThan($initialEventCount, \count($subscription->subscriptionEvents));
    }
}
