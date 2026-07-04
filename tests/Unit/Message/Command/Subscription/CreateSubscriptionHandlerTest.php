<?php

// ABOUTME: Unit tests for CreateSubscriptionHandler verifying subscription creation.
// ABOUTME: Tests owner resolution plus the category- and payment-source branches: found, null, and missing.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Subscription;

use App\Entity\Category;
use App\Entity\PaymentSource;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\Message\Command\Subscription\CreateSubscriptionCommand;
use App\Message\Command\Subscription\CreateSubscriptionHandler;
use App\Repository\CategoryRepository;
use App\Repository\PaymentSourceRepository;
use App\Repository\UserRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class CreateSubscriptionHandlerTest extends TestCase
{
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = new User(email: 'owner@example.com');
    }

    private function command(?Ulid $categoryId, ?Ulid $paymentSourceId = null): CreateSubscriptionCommand
    {
        return new CreateSubscriptionCommand(
            ownerUserId: $this->owner->id,
            categoryId: $categoryId,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2026-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1599,
            currency: Currency::USD,
            color: TileColor::Blue,
            paymentSourceId: $paymentSourceId,
        );
    }

    /** @return UserRepository&\PHPUnit\Framework\MockObject\MockObject */
    private function userRepositoryReturningOwner(): UserRepository
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('find')->with($this->owner->id)->willReturn($this->owner);

        return $userRepository;
    }

    public function testCreatesSubscriptionOwnedByTheResolvedUser(): void
    {
        // An uncategorized, source-less command never touches these, so they are stubs, not mocks.
        $categoryRepository = self::createStub(CategoryRepository::class);
        $paymentSourceRepository = self::createStub(PaymentSourceRepository::class);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::callback(fn (Subscription $s): bool => $s->owner === $this->owner))
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, $paymentSourceRepository, $this->userRepositoryReturningOwner(), $entityManager, $notifier);
        $handler($this->command(null));
    }

    public function testThrowsWhenTheOwnerDoesNotExist(): void
    {
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('find')->willReturn(null);

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::never())->method('findForOwner');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, self::createStub(PaymentSourceRepository::class), $userRepository, $entityManager, $notifier);

        $this->expectException(\InvalidArgumentException::class);
        $handler($this->command(null));
    }

    public function testCreatesSubscriptionWithTheResolvedCategory(): void
    {
        $category = new Category(owner: $this->owner, name: 'Streaming');

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::once())->method('findForOwner')->with($category->id, $this->owner->id)->willReturn($category);

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::never())->method('findForOwner');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static fn (Subscription $s): bool => $s->category === $category))
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, $paymentSourceRepository, $this->userRepositoryReturningOwner(), $entityManager, $notifier);
        $handler($this->command($category->id));
    }

    public function testCreatesSubscriptionWithTheResolvedPaymentSource(): void
    {
        $source = new PaymentSource(owner: $this->owner, name: 'Amex 1234');

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::never())->method('findForOwner');

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::once())->method('findForOwner')->with($source->id, $this->owner->id)->willReturn($source);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static fn (Subscription $s): bool => $s->paymentSource === $source))
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, $paymentSourceRepository, $this->userRepositoryReturningOwner(), $entityManager, $notifier);
        $handler($this->command(null, $source->id));
    }

    public function testThrowsWhenAGivenPaymentSourceDoesNotExist(): void
    {
        $paymentSourceId = new Ulid();

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::never())->method('findForOwner');

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::once())->method('findForOwner')->with($paymentSourceId, $this->owner->id)->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, $paymentSourceRepository, $this->userRepositoryReturningOwner(), $entityManager, $notifier);

        $this->expectException(\InvalidArgumentException::class);
        $handler($this->command(null, $paymentSourceId));
    }

    public function testCreatesAnUncategorizedSubscriptionWhenCategoryIdIsNull(): void
    {
        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::never())->method('findForOwner');

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::never())->method('findForOwner');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static fn (Subscription $s): bool => !$s->category instanceof Category && !$s->paymentSource instanceof PaymentSource))
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, $paymentSourceRepository, $this->userRepositoryReturningOwner(), $entityManager, $notifier);
        $handler($this->command(null));
    }

    public function testThrowsWhenAGivenCategoryDoesNotExist(): void
    {
        $categoryId = new Ulid();

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::once())->method('findForOwner')->with($categoryId, $this->owner->id)->willReturn(null);

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::never())->method('findForOwner');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, $paymentSourceRepository, $this->userRepositoryReturningOwner(), $entityManager, $notifier);

        $this->expectException(\InvalidArgumentException::class);
        $handler($this->command($categoryId));
    }
}
