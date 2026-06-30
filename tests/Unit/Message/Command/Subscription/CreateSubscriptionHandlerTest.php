<?php

// ABOUTME: Unit tests for CreateSubscriptionHandler verifying subscription creation.
// ABOUTME: Tests the category- and payment-source-resolution branches: found, null, and missing.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Subscription;

use App\Entity\Category;
use App\Entity\PaymentSource;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\Message\Command\Subscription\CreateSubscriptionCommand;
use App\Message\Command\Subscription\CreateSubscriptionHandler;
use App\Repository\CategoryRepository;
use App\Repository\PaymentSourceRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class CreateSubscriptionHandlerTest extends TestCase
{
    private static function command(?Ulid $categoryId, ?Ulid $paymentSourceId = null): CreateSubscriptionCommand
    {
        return new CreateSubscriptionCommand(
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

    public function testCreatesSubscriptionWithTheResolvedCategory(): void
    {
        $category = new Category(name: 'Streaming');

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::once())->method('find')->with($category->id)->willReturn($category);

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::never())->method('find');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static fn (Subscription $s): bool => $s->category === $category))
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, $paymentSourceRepository, $entityManager, $notifier);
        $handler(self::command($category->id));
    }

    public function testCreatesSubscriptionWithTheResolvedPaymentSource(): void
    {
        $source = new PaymentSource(name: 'Amex 1234');

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::never())->method('find');

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::once())->method('find')->with($source->id)->willReturn($source);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static fn (Subscription $s): bool => $s->paymentSource === $source))
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, $paymentSourceRepository, $entityManager, $notifier);
        $handler(self::command(null, $source->id));
    }

    public function testThrowsWhenAGivenPaymentSourceDoesNotExist(): void
    {
        $paymentSourceId = new Ulid();

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::never())->method('find');

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::once())->method('find')->with($paymentSourceId)->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, $paymentSourceRepository, $entityManager, $notifier);

        $this->expectException(\InvalidArgumentException::class);
        $handler(self::command(null, $paymentSourceId));
    }

    public function testCreatesAnUncategorizedSubscriptionWhenCategoryIdIsNull(): void
    {
        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::never())->method('find');

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::never())->method('find');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static fn (Subscription $s): bool => null === $s->category && null === $s->paymentSource))
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, $paymentSourceRepository, $entityManager, $notifier);
        $handler(self::command(null));
    }

    public function testThrowsWhenAGivenCategoryDoesNotExist(): void
    {
        $categoryId = new Ulid();

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::once())->method('find')->with($categoryId)->willReturn(null);

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::never())->method('find');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, $paymentSourceRepository, $entityManager, $notifier);

        $this->expectException(\InvalidArgumentException::class);
        $handler(self::command($categoryId));
    }
}
