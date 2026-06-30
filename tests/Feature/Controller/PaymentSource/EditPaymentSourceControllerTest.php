<?php

// ABOUTME: Feature tests for EditPaymentSourceController verifying edit functionality.
// ABOUTME: Tests form pre-fill, successful updates, flash messages, and 404 handling.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\PaymentSource;

use App\Entity\PaymentSource;
use App\Enum\TileColor;
use App\Factory\PaymentSourceFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

final class EditPaymentSourceControllerTest extends WebTestCase
{
    public function testGetRequestDisplaysPrefilledForm(): void
    {
        $client = self::createClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Amex 1234', 'comment' => 'old note']);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/payment-sources/' . $source->id . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Edit Payment Source');
        self::assertSame('Amex 1234', $crawler->filter('input[name="payment_source[name]"]')->attr('value'));
        self::assertSame('old note', trim($crawler->filter('textarea[name="payment_source[comment]"]')->text()));
    }

    public function testUpdatesNameCommentAndColor(): void
    {
        $client = self::createClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Old Name', 'comment' => 'old']);
        $sourceId = $source->id;

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/payment-sources/' . $sourceId . '/edit');
        $form = $crawler->selectButton(value: 'Save')->form();
        $form['payment_source[name]'] = 'Amex 5678';
        $form['payment_source[comment]'] = 'reissued';
        $form['payment_source[color]'] = 'teal';

        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/payment-sources/' . $sourceId);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(id: EntityManagerInterface::class);
        $entityManager->clear();

        $updated = $entityManager->getRepository(PaymentSource::class)->find($sourceId);

        self::assertNotNull($updated);
        self::assertSame('Amex 5678', $updated->name);
        self::assertSame('reissued', $updated->comment);
        self::assertSame(TileColor::Teal, $updated->color);
    }

    public function testUpdateShowsSuccessFlashMessage(): void
    {
        $client = self::createClient();

        $source = PaymentSourceFactory::createOne(['name' => 'Source']);

        $crawler = $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/payment-sources/' . $source->id . '/edit');
        $form = $crawler->selectButton(value: 'Save')->form();
        $form['payment_source[name]'] = 'Renamed';
        $client->submit(form: $form);
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-success', text: 'Payment source updated successfully');
    }

    public function testReturns404ForNonExistentPaymentSource(): void
    {
        $client = self::createClient();

        $client->request(method: \Symfony\Component\HttpFoundation\Request::METHOD_GET, uri: '/payment-sources/' . new Ulid() . '/edit');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }
}
