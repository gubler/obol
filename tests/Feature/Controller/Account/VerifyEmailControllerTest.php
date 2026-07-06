<?php

// ABOUTME: Feature tests for the public secondary-email verify endpoint - valid link, tampered/expired, conflict, idempotence.
// ABOUTME: A valid signed link flips the pending row to verified; a tampered or expired one is indistinguishable from a miss.

declare(strict_types=1);

namespace App\Tests\Feature\Controller\Account;

use App\Entity\User;
use App\Entity\UserEmail;
use App\Factory\UserEmailFactory;
use App\Factory\UserFactory;
use App\Repository\UserEmailRepository;
use App\Security\SecondaryEmailVerifyUriSigner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class VerifyEmailControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private SecondaryEmailVerifyUriSigner $signer;

    private UserEmailRepository $userEmails;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
        $this->signer = self::getContainer()->get(SecondaryEmailVerifyUriSigner::class);
        $this->userEmails = self::getContainer()->get(UserEmailRepository::class);
    }

    public function testAValidSignedLinkVerifiesThePendingAddress(): void
    {
        $user = UserFactory::createOne(['email' => 'owner@dev88.test']);
        $pending = UserEmailFactory::new()->unverified()->create(['user' => $user, 'email' => 'pending@dev88.test']);

        $this->client->request(Request::METHOD_GET, $this->signedLink($pending));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-test="email-verified"]');
        self::assertTrue($this->reload($pending->id)->isVerified());
    }

    public function testATamperedSignatureIsForbiddenAndLeavesTheAddressPending(): void
    {
        $user = UserFactory::createOne(['email' => 'owner@dev88.test']);
        $pending = UserEmailFactory::new()->unverified()->create(['user' => $user, 'email' => 'pending@dev88.test']);

        $tampered = $this->signedLink($pending) . 'x';
        $this->client->request(Request::METHOD_GET, $tampered);

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->reload($pending->id)->isVerified());
    }

    public function testAnExpiredSignatureIsForbidden(): void
    {
        $user = UserFactory::createOne(['email' => 'owner@dev88.test']);
        $pending = UserEmailFactory::new()->unverified()->create(['user' => $user, 'email' => 'pending@dev88.test']);

        $expired = $this->signer->sign($pending, new \DateTimeImmutable('-1 hour'));
        $this->client->request(Request::METHOD_GET, $expired);

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->reload($pending->id)->isVerified());
    }

    public function testAValidSignatureForAMissingRowIs404(): void
    {
        // Sign a link for a transient, never-persisted row: the signature is valid but the id resolves to nothing.
        $ghostOwner = new User(email: 'ghost@dev88.test');
        $ghost = new UserEmail(user: $ghostOwner, email: 'ghost-secondary@dev88.test', isPrimary: false, verifiedAt: null);

        $this->client->request(Request::METHOD_GET, $this->signer->sign($ghost, new \DateTimeImmutable('+1 hour')));

        self::assertResponseStatusCodeSame(404);
    }

    public function testReClickingAnAlreadyVerifiedLinkIsIdempotent(): void
    {
        $user = UserFactory::createOne(['email' => 'owner@dev88.test']);
        $pending = UserEmailFactory::new()->unverified()->create(['user' => $user, 'email' => 'pending@dev88.test']);
        $link = $this->signedLink($pending);

        $this->client->request(Request::METHOD_GET, $link);
        self::assertResponseIsSuccessful();

        $this->client->request(Request::METHOD_GET, $link);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->reload($pending->id)->isVerified());
    }

    public function testVerifyingAnAddressAnotherUserOwnsConflicts(): void
    {
        $owner = UserFactory::createOne(['email' => 'owner@dev88.test']);
        $pending = UserEmailFactory::new()->unverified()->create(['user' => $owner, 'email' => 'contested@dev88.test']);

        $rival = UserFactory::createOne(['email' => 'rival@dev88.test']);
        UserEmailFactory::createOne(['user' => $rival, 'email' => 'contested@dev88.test', 'verifiedAt' => new \DateTimeImmutable()]);

        $this->client->request(Request::METHOD_GET, $this->signedLink($pending));

        self::assertResponseStatusCodeSame(409);
        self::assertFalse($this->reload($pending->id)->isVerified());
    }

    private function signedLink(UserEmail $userEmail): string
    {
        return $this->signer->sign($userEmail, new \DateTimeImmutable('+1 hour'));
    }

    private function reload(\Symfony\Component\Uid\Ulid $id): UserEmail
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $row = $this->userEmails->find($id);
        self::assertInstanceOf(UserEmail::class, $row);

        return $row;
    }
}
