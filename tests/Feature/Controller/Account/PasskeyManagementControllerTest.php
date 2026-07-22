<?php

// ABOUTME: Feature tests for the /app/account/passkeys management surface - list, register page, rename, revoke.
// ABOUTME: Covers owner isolation (a passkey owned by another user 404s) and the empty/populated list states.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Account;

use App\Entity\PasskeyCredential;
use App\Factory\PasskeyCredentialFactory;
use App\Factory\UserFactory;
use App\Tests\Support\AuthenticatedTestCase;
use App\Tests\Support\SameOriginPostTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Ulid;

final class PasskeyManagementControllerTest extends AuthenticatedTestCase
{
    use SameOriginPostTrait;

    public function testListShowsTheEmptyStateWhenTheUserHasNoPasskeys(): void
    {
        $client = $this->authenticatedClient();

        $client->request(Request::METHOD_GET, '/app/account/access');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="passkey-index-empty"]');
    }

    public function testListShowsTheUsersPasskeys(): void
    {
        $client = $this->authenticatedClient();
        PasskeyCredentialFactory::createOne(['user' => UserFactory::founder(), 'name' => 'My Phone']);

        $crawler = $client->request(Request::METHOD_GET, '/app/account/access');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-test="passkey-row"]'));
        self::assertSelectorTextContains('[data-test="passkey-name-input"]', '');
        self::assertStringContainsString('My Phone', (string) $crawler->filter('[data-test="passkey-name-input"]')->attr('value'));
    }

    public function testRegisterPageRenders(): void
    {
        $client = $this->authenticatedClient();

        $client->request(Request::METHOD_GET, '/app/account/passkeys/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="passkey-register"]');
        self::assertSelectorExists('[data-test="passkey-register-submit"]');
    }

    public function testRevokeRemovesOwnPasskeyAndRedirects(): void
    {
        $client = $this->authenticatedClient();
        $passkey = PasskeyCredentialFactory::createOne(['user' => UserFactory::founder(), 'name' => 'Doomed']);
        $id = $passkey->id;

        $this->postSameOrigin($client, '/app/account/passkeys/' . $id . '/delete');

        self::assertResponseRedirects('/app/account/access');

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($em->getRepository(PasskeyCredential::class)->find($id));
    }

    public function testRevokeOfTheLastPasskeyWarns(): void
    {
        $client = $this->authenticatedClient();
        $passkey = PasskeyCredentialFactory::createOne(['user' => UserFactory::founder(), 'name' => 'Only One']);

        $this->postSameOrigin($client, '/app/account/passkeys/' . $passkey->id . '/delete');
        $client->followRedirect();

        self::assertSelectorExists('.flash-warning');
    }

    public function testRevokeOfAnotherUsersPasskeyIs404(): void
    {
        $client = $this->authenticatedClient();
        $otherUser = UserFactory::createOne();
        $passkey = PasskeyCredentialFactory::createOne(['user' => $otherUser, 'name' => 'Not Yours']);
        $id = $passkey->id;

        $this->postSameOrigin($client, '/app/account/passkeys/' . $id . '/delete');

        self::assertResponseStatusCodeSame(404);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertNotNull($em->getRepository(PasskeyCredential::class)->find($id));
    }

    public function testRevokeOnlyAcceptsPost(): void
    {
        $client = $this->authenticatedClient();
        $passkey = PasskeyCredentialFactory::createOne(['user' => UserFactory::founder()]);

        $client->request(Request::METHOD_GET, '/app/account/passkeys/' . $passkey->id . '/delete');

        self::assertResponseStatusCodeSame(405);
    }

    public function testRenameChangesTheNameAndRedirects(): void
    {
        $client = $this->authenticatedClient();
        $passkey = PasskeyCredentialFactory::createOne(['user' => UserFactory::founder(), 'name' => 'Old Name']);
        $id = $passkey->id;

        $crawler = $client->request(Request::METHOD_GET, '/app/account/access');
        $form = $crawler->filter('form[action="/app/account/passkeys/' . $id . '/name"]')->form();
        $form['rename_passkey[name]'] = 'New Name';
        $client->submit($form);

        self::assertResponseRedirects('/app/account/access');

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $reloaded = $em->getRepository(PasskeyCredential::class)->find($id);
        self::assertInstanceOf(PasskeyCredential::class, $reloaded);
        self::assertSame('New Name', $reloaded->name);
    }

    public function testRenameOfAnotherUsersPasskeyIs404(): void
    {
        $client = $this->authenticatedClient();
        $otherUser = UserFactory::createOne();
        $passkey = PasskeyCredentialFactory::createOne(['user' => $otherUser, 'name' => 'Not Yours']);

        $client->request(Request::METHOD_POST, '/app/account/passkeys/' . $passkey->id . '/name', [
            'rename_passkey' => ['name' => 'Hijacked'],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    public function testManagementSurfaceRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request(Request::METHOD_GET, '/app/account/access');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testRenameOfANonExistentPasskeyIs404(): void
    {
        $client = $this->authenticatedClient();

        $client->request(Request::METHOD_POST, '/app/account/passkeys/' . new Ulid() . '/name', [
            'rename_passkey' => ['name' => 'Ghost'],
        ]);

        self::assertResponseStatusCodeSame(404);
    }
}
