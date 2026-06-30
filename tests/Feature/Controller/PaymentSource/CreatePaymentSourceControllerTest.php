<?php

// ABOUTME: Feature tests for CreatePaymentSourceController verifying creation functionality.
// ABOUTME: Tests form rendering, the color picker, comment, validation, and successful creation with redirects.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\PaymentSource;

use App\Entity\PaymentSource;
use App\Enum\TileColor;
use App\Factory\PaymentSourceFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreatePaymentSourceControllerTest extends WebTestCase
{
    public function testGetRequestDisplaysCreateForm(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/payment-sources/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'New Payment Source');
        self::assertSelectorExists(selector: 'form');
        self::assertSelectorExists(selector: 'input[name="payment_source[name]"]');
        self::assertSelectorExists(selector: 'textarea[name="payment_source[comment]"]');
        self::assertSelectorExists(selector: 'button[type="submit"]');
    }

    public function testRendersAColorSwatchPicker(): void
    {
        $client = self::createClient();

        $crawler = $client->request(method: 'GET', uri: '/payment-sources/new');

        self::assertResponseIsSuccessful();
        // The swatch picker mirrors the subscription form: one radio per TileColor, one pre-selected.
        self::assertCount(18, $crawler->filter('input[type="radio"][name="payment_source[color]"]'));
        self::assertCount(1, $crawler->filter('input[type="radio"][name="payment_source[color]"]:checked'));
    }

    public function testPersistsTheChosenColorAndComment(): void
    {
        $client = self::createClient();

        $crawler = $client->request(method: 'GET', uri: '/payment-sources/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['payment_source[name]'] = 'Amex 1234';
        $form['payment_source[comment]'] = 'Expires 09/27';
        $form['payment_source[color]'] = 'violet';

        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/payment-sources');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);
        $source = $entityManager->getRepository(PaymentSource::class)->findOneBy(['name' => 'Amex 1234']);

        self::assertNotNull($source);
        self::assertSame('Expires 09/27', $source->comment);
        self::assertSame(TileColor::Violet, $source->color);
    }

    public function testShowsCancelLinkBackToIndex(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/payment-sources/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/payment-sources"]');
    }

    public function testPostRequestWithValidDataShowsSuccessFlashMessage(): void
    {
        $client = self::createClient();

        $crawler = $client->request(method: 'GET', uri: '/payment-sources/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['payment_source[name]'] = 'Flash Test Source';

        $client->submit(form: $form);
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-success', text: 'Payment source created successfully');
    }

    public function testPostRequestWithEmptyNameShowsValidationError(): void
    {
        $client = self::createClient();

        $crawler = $client->request(method: 'GET', uri: '/payment-sources/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['payment_source[name]'] = '';

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.form-error');
        self::assertSelectorTextContains(selector: 'body', text: 'This value should not be blank');
    }

    public function testPostRequestWithTooLongNameShowsValidationError(): void
    {
        $client = self::createClient();

        $crawler = $client->request(method: 'GET', uri: '/payment-sources/new');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['payment_source[name]'] = str_repeat(string: 'a', times: 256);

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.form-error');
        self::assertSelectorTextContains(selector: 'body', text: 'This value is too long');
    }

    public function testFormIncludesCsrfProtection(): void
    {
        $client = self::createClient();

        $client->request(method: 'GET', uri: '/payment-sources/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'input[name="payment_source[_token]"]');
    }

    public function testPostRequestDoesNotCreatePaymentSourceWhenValidationFails(): void
    {
        $client = self::createClient();

        $crawler = $client->request(method: 'GET', uri: '/payment-sources/new');

        $initialCount = PaymentSourceFactory::count();

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['payment_source[name]'] = '';

        $client->submit(form: $form);

        self::assertSame($initialCount, PaymentSourceFactory::count());
    }
}
