<?php

// ABOUTME: Feature tests for the /app/account/emails per-row actions - promote, remove, resend - through the firewall.
// ABOUTME: The promote test pins the key property: swapping your own primary keeps the acting session signed in.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Account;

use App\Entity\UserEmail;
use App\Factory\UserEmailFactory;
use App\Factory\UserFactory;
use App\Repository\UserEmailRepository;
use App\Repository\UserRepository;
use App\Tests\Support\SameOriginPostTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class EmailActionsControllerTest extends WebTestCase
{
    use SameOriginPostTrait;

    public function testPromotingAVerifiedSecondaryUpdatesThePrimaryAndKeepsTheSessionAlive(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'old@dev88.test']);
        $secondary = UserEmailFactory::createOne(['user' => $user, 'email' => 'new@dev88.test', 'verifiedAt' => new \DateTimeImmutable()]);
        $client->loginUser($user);

        $this->postSameOrigin($client, '/app/account/emails/' . $secondary->id . '/promote');
        self::assertResponseRedirects('/app/account/access');

        // Following the redirect hits an authenticated-by-default route. A successful render (not a bounce
        // to /login) proves the acting session survived the primary-email change.
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.flash-success');

        $users = self::getContainer()->get(UserRepository::class);
        self::assertSame('new@dev88.test', $users->getForId($user->id)->email);
    }

    public function testPromotingAnUnverifiedAddressFlashesAnError(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        $pending = UserEmailFactory::new()->unverified()->create(['user' => $user, 'email' => 'pending@dev88.test']);
        $client->loginUser($user);

        $this->postSameOrigin($client, '/app/account/emails/' . $pending->id . '/promote');
        self::assertResponseRedirects('/app/account/access');
        $client->followRedirect();
        self::assertSelectorExists('.flash-error');

        $users = self::getContainer()->get(UserRepository::class);
        self::assertSame('primary@dev88.test', $users->getForId($user->id)->email);
    }

    public function testPromotingAnotherUsersAddressIs404(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'me@dev88.test']);
        $other = UserFactory::createOne(['email' => 'other@dev88.test']);
        $theirs = UserEmailFactory::createOne(['user' => $other, 'email' => 'theirs@dev88.test', 'verifiedAt' => new \DateTimeImmutable()]);
        $client->loginUser($user);

        $this->postSameOrigin($client, '/app/account/emails/' . $theirs->id . '/promote');
        self::assertResponseStatusCodeSame(404);
    }

    public function testRemovingASecondaryDeletesItAndRedirects(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        $secondary = UserEmailFactory::createOne(['user' => $user, 'email' => 'spare@dev88.test', 'verifiedAt' => new \DateTimeImmutable()]);
        $id = $secondary->id;
        $client->loginUser($user);

        $this->postSameOrigin($client, '/app/account/emails/' . $id . '/remove');
        self::assertResponseRedirects('/app/account/access');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($em->getRepository(UserEmail::class)->find($id));
    }

    public function testRemovingThePrimaryIsBlockedWithAnError(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        $client->loginUser($user);

        $primary = self::getContainer()->get(UserEmailRepository::class)->findPrimaryForUser($user);
        $this->postSameOrigin($client, '/app/account/emails/' . $primary->id . '/remove');

        self::assertResponseRedirects('/app/account/access');
        $client->followRedirect();
        self::assertSelectorExists('.flash-error');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertNotNull($em->getRepository(UserEmail::class)->find($primary->id));
    }

    public function testRemovingAnotherUsersAddressIs404(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'me@dev88.test']);
        $other = UserFactory::createOne(['email' => 'other@dev88.test']);
        $theirs = UserEmailFactory::createOne(['user' => $other, 'email' => 'theirs@dev88.test', 'verifiedAt' => new \DateTimeImmutable()]);
        $client->loginUser($user);

        $this->postSameOrigin($client, '/app/account/emails/' . $theirs->id . '/remove');
        self::assertResponseStatusCodeSame(404);
    }

    public function testResendingForAPendingAddressRedirectsWithANotice(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        $pending = UserEmailFactory::new()->unverified()->create(['user' => $user, 'email' => 'pending@dev88.test']);
        $client->loginUser($user);

        $this->postSameOrigin($client, '/app/account/emails/' . $pending->id . '/resend');
        self::assertResponseRedirects('/app/account/access');
        $client->followRedirect();
        self::assertSelectorExists('.flash-notice');
    }

    public function testActionsOnlyAcceptPost(): void
    {
        $client = self::createClient();
        $user = UserFactory::createOne(['email' => 'primary@dev88.test']);
        $secondary = UserEmailFactory::createOne(['user' => $user, 'email' => 'spare@dev88.test', 'verifiedAt' => new \DateTimeImmutable()]);
        $client->loginUser($user);

        $client->request(Request::METHOD_GET, '/app/account/emails/' . $secondary->id . '/promote');
        self::assertResponseStatusCodeSame(405);
    }
}
