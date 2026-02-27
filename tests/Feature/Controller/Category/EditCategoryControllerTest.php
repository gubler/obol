<?php

// ABOUTME: Feature tests for EditCategoryController verifying category update functionality.
// ABOUTME: Tests ensure proper form pre-population, validation, and successful updates with redirects.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Category;

use App\Entity\Category;
use App\Factory\CategoryFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Ulid;

class EditCategoryControllerTest extends WebTestCase
{
    public function testGetRequestDisplaysEditFormWithCurrentData(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Original Name']);
        $categoryId = $category->id;

        $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(selector: 'h1', text: 'Edit Category');
        self::assertSelectorExists(selector: 'form');
        self::assertSelectorExists(selector: 'input[name="edit_category[name]"][value="Original Name"]');
        self::assertSelectorExists(selector: 'button[type="submit"]');
    }

    public function testShowsCancelLinkBackToShowPage(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $categoryId = $category->id;

        $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'a[href="/categories/' . $categoryId . '"]');
    }

    public function testPostRequestWithValidDataUpdatesCategory(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Old Name']);
        $categoryId = $category->id;

        $crawler = $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['edit_category[name]'] = 'Updated Name';

        $client->submit(form: $form);

        self::assertResponseRedirects(expectedLocation: '/categories/' . $categoryId);

        $container = static::getContainer();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(id: EntityManagerInterface::class);
        $repository = $entityManager->getRepository(className: Category::class);

        $updatedCategory = $repository->find($categoryId);

        self::assertNotNull($updatedCategory);
        self::assertSame('Updated Name', $updatedCategory->name);
    }

    public function testPostRequestWithValidDataShowsSuccessFlashMessage(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $categoryId = $category->id;

        $crawler = $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['edit_category[name]'] = 'Updated Category';

        $client->submit(form: $form);
        $client->followRedirect();

        self::assertSelectorTextContains(selector: '.flash-success', text: 'Category updated successfully');
    }

    public function testPostRequestWithEmptyNameShowsValidationError(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $categoryId = $category->id;

        $crawler = $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['edit_category[name]'] = '';

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.form-error');
        self::assertSelectorTextContains(selector: 'body', text: 'This value should not be blank');
    }

    public function testPostRequestWithTooLongNameShowsValidationError(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $categoryId = $category->id;

        $crawler = $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');

        $form = $crawler->selectButton(value: 'Save')->form();
        $form['edit_category[name]'] = str_repeat(string: 'a', times: 256);

        $client->submit(form: $form);

        self::assertResponseStatusCodeSame(expectedCode: 422);
        self::assertSelectorExists(selector: '.form-error');
        self::assertSelectorTextContains(selector: 'body', text: 'This value is too long');
    }

    public function testReturns404ForNonExistentCategory(): void
    {
        $client = static::createClient();

        $nonExistentId = new Ulid();

        $client->request(method: 'GET', uri: '/categories/' . $nonExistentId . '/edit');

        self::assertResponseStatusCodeSame(expectedCode: 404);
    }

    public function testFormIncludesCsrfProtection(): void
    {
        $client = static::createClient();

        $category = CategoryFactory::createOne(['name' => 'Test Category']);
        $categoryId = $category->id;

        $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(selector: 'input[name="edit_category[_token]"]');
    }
}
