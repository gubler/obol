<?php

// ABOUTME: Feature test proving per-user data isolation: one user never sees or mutates another's data.
// ABOUTME: User B gets a 404 on user A's subscription and payment across read, write, and report routes.

declare(strict_types=1);

namespace App\Tests\Feature\Controller;

use App\Factory\CategoryFactory;
use App\Factory\PaymentFactory;
use App\Factory\PaymentSourceFactory;
use App\Factory\SubscriptionFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class OwnerIsolationTest extends WebTestCase
{
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
            $client->request(method: Request::METHOD_POST, uri: $uri);
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

        $client->request(method: Request::METHOD_POST, uri: '/app/categories/' . $category->id . '/delete');
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
            $client->request(method: Request::METHOD_POST, uri: $uri);
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

    private function loginAsAnotherUser(KernelBrowser $client): void
    {
        $client->loginUser(UserFactory::createOne());
    }
}
