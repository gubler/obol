<?php

// ABOUTME: Feature tests for EditCategoryController verifying category update functionality.
// ABOUTME: Tests ensure proper form pre-population, validation, and successful updates with redirects.

declare(strict_types=1);

use App\Entity\Category;
use App\Factory\CategoryFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Ulid;

test('get request displays edit form with current data', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Original Name']);
    $categoryId = $category->id;

    $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains(selector: 'h1', text: 'Edit Category');
    $this->assertSelectorExists(selector: 'form');
    $this->assertSelectorExists(selector: 'input[name="edit_category[name]"][value="Original Name"]');
    $this->assertSelectorExists(selector: 'button[type="submit"]');
});

test('shows cancel link back to show page', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Test Category']);
    $categoryId = $category->id;

    $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'a[href="/categories/' . $categoryId . '"]');
});

test('post request with valid data updates category', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Old Name']);
    $categoryId = $category->id;

    $crawler = $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');

    $form = $crawler->selectButton(value: 'Save')->form();
    $form['edit_category[name]'] = 'Updated Name';

    $client->submit(form: $form);

    $this->assertResponseRedirects(expectedLocation: '/categories/' . $categoryId);

    $container = $this->getContainer();
    /** @var EntityManagerInterface $entityManager */
    $entityManager = $container->get(id: EntityManagerInterface::class);
    $repository = $entityManager->getRepository(className: Category::class);

    $updatedCategory = $repository->find($categoryId);

    expect($updatedCategory)->not->toBeNull();
    expect($updatedCategory->name)->toBe('Updated Name');
});

test('post request with valid data shows success flash message', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Test Category']);
    $categoryId = $category->id;

    $crawler = $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');

    $form = $crawler->selectButton(value: 'Save')->form();
    $form['edit_category[name]'] = 'Updated Category';

    $client->submit(form: $form);
    $client->followRedirect();

    $this->assertSelectorTextContains(selector: '.flash-success', text: 'Category updated successfully');
});

test('post request with empty name shows validation error', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Test Category']);
    $categoryId = $category->id;

    $crawler = $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');

    $form = $crawler->selectButton(value: 'Save')->form();
    $form['edit_category[name]'] = '';

    $client->submit(form: $form);

    $this->assertResponseStatusCodeSame(expectedCode: 422);
    $this->assertSelectorExists(selector: '.form-error');
    $this->assertSelectorTextContains(selector: 'body', text: 'This value should not be blank');
});

test('post request with too long name shows validation error', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Test Category']);
    $categoryId = $category->id;

    $crawler = $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');

    $form = $crawler->selectButton(value: 'Save')->form();
    $form['edit_category[name]'] = str_repeat(string: 'a', times: 256);

    $client->submit(form: $form);

    $this->assertResponseStatusCodeSame(expectedCode: 422);
    $this->assertSelectorExists(selector: '.form-error');
    $this->assertSelectorTextContains(selector: 'body', text: 'This value is too long');
});

test('returns 404 for non existent category', function (): void {
    $client = $this->createClient();

    $nonExistentId = new Ulid();

    $client->request(method: 'GET', uri: '/categories/' . $nonExistentId . '/edit');

    $this->assertResponseStatusCodeSame(expectedCode: 404);
});

test('form includes csrf protection', function (): void {
    $client = $this->createClient();

    $category = CategoryFactory::createOne(['name' => 'Test Category']);
    $categoryId = $category->id;

    $client->request(method: 'GET', uri: '/categories/' . $categoryId . '/edit');

    $this->assertResponseIsSuccessful();
    $this->assertSelectorExists(selector: 'input[name="edit_category[_token]"]');
});
