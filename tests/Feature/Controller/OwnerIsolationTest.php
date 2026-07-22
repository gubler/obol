<?php

// ABOUTME: Feature test proving per-user data isolation: one user never sees or mutates another's data.
// ABOUTME: User B gets a 404 on user A's subscription and payment across read, write, and report routes.

declare(strict_types=1);

namespace App\Tests\Feature\Controller;

use App\Entity\Subscription;
use App\Factory\CategoryFactory;
use App\Factory\PaymentFactory;
use App\Factory\PaymentSourceFactory;
use App\Factory\SubscriptionFactory;
use App\Factory\UserFactory;
use App\Tests\Support\SameOriginPostTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class OwnerIsolationTest extends WebTestCase
{
    use SameOriginPostTrait;

    public function testUserBCannotReadUserAsSubscriptionOrPayment(): void
    {
        // createClient() must be the first kernel boot; the factories reuse it afterwards.
        $client = self::createClient();
        $userA = UserFactory::createOne();
        $subscription = SubscriptionFactory::createOne(['owner' => $userA, 'name' => 'Aardvark Weekly']);
        $payment = PaymentFactory::createOne(['subscription' => $subscription]);
        $this->loginAsAnotherUser($client);

        foreach ([
            '/app/subscriptions/' . $subscription->id,
            '/app/subscriptions/' . $subscription->id . '/edit',
            '/app/payments/' . $payment->id . '/edit',
        ] as $uri) {
            $client->request(method: Request::METHOD_GET, uri: $uri);
            self::assertResponseStatusCodeSame(expectedCode: 404, message: $uri . ' must 404 for a non-owner');
        }
    }

    public function testUserBCannotMutateUserAsSubscriptionOrPayment(): void
    {
        $client = self::createClient();
        $userA = UserFactory::createOne();
        $subscription = SubscriptionFactory::createOne(['owner' => $userA]);
        $payment = PaymentFactory::createOne(['subscription' => $subscription]);
        $this->loginAsAnotherUser($client);

        foreach ([
            '/app/subscriptions/' . $subscription->id . '/archive',
            '/app/subscriptions/' . $subscription->id . '/unarchive',
            '/app/subscriptions/' . $subscription->id . '/delete',
            '/app/payments/' . $payment->id . '/validate',
            '/app/payments/' . $payment->id . '/delete',
        ] as $uri) {
            $this->postSameOrigin($client, $uri);
            self::assertResponseStatusCodeSame(expectedCode: 404, message: $uri . ' must 404 for a non-owner');
        }
    }

    public function testUserBsListingAndReportsExcludeUserAsData(): void
    {
        $client = self::createClient();
        $userA = UserFactory::createOne();
        SubscriptionFactory::createOne(['owner' => $userA, 'name' => 'Aardvark Weekly']);
        $this->loginAsAnotherUser($client);

        $client->request(method: Request::METHOD_GET, uri: '/app');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains(selector: 'body', text: 'Aardvark Weekly');

        $client->request(method: Request::METHOD_GET, uri: '/app/reports');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains(selector: 'body', text: 'Aardvark Weekly');
    }

    public function testUserBCannotReadOrMutateUserAsCategory(): void
    {
        $client = self::createClient();
        $userA = UserFactory::createOne();
        $category = CategoryFactory::createOne(['owner' => $userA, 'name' => 'Aardvark Media']);
        $this->loginAsAnotherUser($client);

        foreach ([
            '/app/categories/' . $category->id,
            '/app/categories/' . $category->id . '/edit',
        ] as $uri) {
            $client->request(method: Request::METHOD_GET, uri: $uri);
            self::assertResponseStatusCodeSame(expectedCode: 404, message: $uri . ' must 404 for a non-owner');
        }

        $this->postSameOrigin($client, '/app/categories/' . $category->id . '/delete');
        self::assertResponseStatusCodeSame(expectedCode: 404, message: 'deleting a non-owned category must 404');
    }

    public function testUserBCannotReadOrMutateUserAsPaymentSource(): void
    {
        $client = self::createClient();
        $userA = UserFactory::createOne();
        $source = PaymentSourceFactory::createOne(['owner' => $userA, 'name' => 'Aardvark Amex']);
        $this->loginAsAnotherUser($client);

        foreach ([
            '/app/payment-sources/' . $source->id,
            '/app/payment-sources/' . $source->id . '/edit',
        ] as $uri) {
            $client->request(method: Request::METHOD_GET, uri: $uri);
            self::assertResponseStatusCodeSame(expectedCode: 404, message: $uri . ' must 404 for a non-owner');
        }

        foreach ([
            '/app/payment-sources/' . $source->id . '/delete',
            '/app/payment-sources/' . $source->id . '/reassign',
        ] as $uri) {
            $this->postSameOrigin($client, $uri);
            self::assertResponseStatusCodeSame(expectedCode: 404, message: $uri . ' must 404 for a non-owner');
        }
    }

    public function testUserBsCategoryAndPaymentSourceListingsExcludeUserAsData(): void
    {
        $client = self::createClient();
        $userA = UserFactory::createOne();
        CategoryFactory::createOne(['owner' => $userA, 'name' => 'Aardvark Media']);
        PaymentSourceFactory::createOne(['owner' => $userA, 'name' => 'Aardvark Amex']);
        $this->loginAsAnotherUser($client);

        $client->request(method: Request::METHOD_GET, uri: '/app/categories');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains(selector: 'body', text: 'Aardvark Media');

        $client->request(method: Request::METHOD_GET, uri: '/app/payment-sources');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains(selector: 'body', text: 'Aardvark Amex');
    }

    public function testSubscriptionCreateFormPickersExcludeAnotherUsersCategoriesAndPaymentSources(): void
    {
        $client = self::createClient();
        $userA = UserFactory::createOne();
        CategoryFactory::createOne(['owner' => $userA, 'name' => 'Aardvark Media']);
        PaymentSourceFactory::createOne(['owner' => $userA, 'name' => 'Aardvark Amex']);

        // User B needs their own category and source so the pickers render at all.
        $userB = UserFactory::createOne();
        CategoryFactory::createOne(['owner' => $userB, 'name' => 'Bumblebee Media']);
        PaymentSourceFactory::createOne(['owner' => $userB, 'name' => 'Bumblebee Bank']);
        $client->loginUser($userB);

        $crawler = $client->request(method: Request::METHOD_GET, uri: '/app/subscriptions/new');
        self::assertResponseIsSuccessful();

        $categories = $crawler->filter('select[name="create_subscription[category]"]');
        self::assertStringContainsString('Bumblebee Media', $categories->text());
        self::assertStringNotContainsString('Aardvark Media', $categories->text(), "user B's category picker must not list user A's categories");

        $sources = $crawler->filter('select[name="create_subscription[paymentSource]"]');
        self::assertStringContainsString('Bumblebee Bank', $sources->text());
        self::assertStringNotContainsString('Aardvark Amex', $sources->text(), "user B's payment-source picker must not list user A's sources");
    }

    public function testSubscriptionEditFormPickersExcludeAnotherUsersCategoriesAndPaymentSources(): void
    {
        $client = self::createClient();
        $userA = UserFactory::createOne();
        CategoryFactory::createOne(['owner' => $userA, 'name' => 'Aardvark Media']);
        PaymentSourceFactory::createOne(['owner' => $userA, 'name' => 'Aardvark Amex']);

        $userB = UserFactory::createOne();
        CategoryFactory::createOne(['owner' => $userB, 'name' => 'Bumblebee Media']);
        PaymentSourceFactory::createOne(['owner' => $userB, 'name' => 'Bumblebee Bank']);
        $subscription = SubscriptionFactory::createOne(['owner' => $userB, 'category' => null]);
        $client->loginUser($userB);

        $crawler = $client->request(method: Request::METHOD_GET, uri: '/app/subscriptions/' . $subscription->id . '/edit');
        self::assertResponseIsSuccessful();

        $categories = $crawler->filter('select[name="edit_subscription[category]"]');
        self::assertStringContainsString('Bumblebee Media', $categories->text());
        self::assertStringNotContainsString('Aardvark Media', $categories->text(), "user B's category picker must not list user A's categories");

        $sources = $crawler->filter('select[name="edit_subscription[paymentSource]"]');
        self::assertStringContainsString('Bumblebee Bank', $sources->text());
        self::assertStringNotContainsString('Aardvark Amex', $sources->text(), "user B's payment-source picker must not list user A's sources");
    }

    public function testCraftedPostWithAnotherUsersCategoryIsRejectedAsInvalidNotErrored(): void
    {
        $client = self::createClient();
        $userA = UserFactory::createOne();
        $categoryA = CategoryFactory::createOne(['owner' => $userA, 'name' => 'Aardvark Media']);

        // User B needs a category of their own so the picker (and its CSRF token) render.
        $userB = UserFactory::createOne();
        CategoryFactory::createOne(['owner' => $userB, 'name' => 'Bumblebee Media']);
        $client->loginUser($userB);

        $crawler = $client->request(method: Request::METHOD_GET, uri: '/app/subscriptions/new');
        self::assertResponseIsSuccessful();

        // Start from the form's real defaults (currency, color, CSRF token) and forge only the
        // category to user A's id. A crafted POST like this must not pass the (now owner-scoped) choice
        // set: it re-renders as a validation error (422), never a 500 from the handler rejecting the
        // cross-owner id, and creates nothing.
        $form = $crawler->selectButton(value: 'Save')->form();
        $values = $form->getPhpValues();
        $values['create_subscription']['category'] = $categoryA->id->toBase32();
        $values['create_subscription']['name'] = 'Smuggled Sub';
        $values['create_subscription']['nextRenewal'] = '2026-01-15';
        $values['create_subscription']['cost'] = '9.99';

        $client->request(method: Request::METHOD_POST, uri: $form->getUri(), parameters: $values);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertNull(
            self::getContainer()->get(EntityManagerInterface::class)->getRepository(Subscription::class)->findOneBy(['name' => 'Smuggled Sub']),
            'a subscription must not be created from a cross-owner category id',
        );
    }

    private function loginAsAnotherUser(KernelBrowser $client): void
    {
        $client->loginUser(UserFactory::createOne());
    }
}
