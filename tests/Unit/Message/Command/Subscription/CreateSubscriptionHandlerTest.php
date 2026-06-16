<?php

// ABOUTME: Unit tests for CreateSubscriptionHandler verifying subscription creation.
// ABOUTME: Tests the category-resolution branches: a found category, a null category, and a missing one.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Subscription;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\Message\Command\Subscription\CreateSubscriptionCommand;
use App\Message\Command\Subscription\CreateSubscriptionHandler;
use App\Repository\CategoryRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class CreateSubscriptionHandlerTest extends TestCase
{
    private static function command(?Ulid $categoryId): CreateSubscriptionCommand
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
        );
    }

    public function testCreatesSubscriptionWithTheResolvedCategory(): void
    {
        $category = new Category(name: 'Streaming');

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::once())->method('find')->with($category->id)->willReturn($category);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static fn (Subscription $s): bool => $s->category === $category))
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, $entityManager, $notifier);
        $handler(self::command($category->id));
    }

    public function testCreatesAnUncategorizedSubscriptionWhenCategoryIdIsNull(): void
    {
        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::never())->method('find');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static fn (Subscription $s): bool => null === $s->category))
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, $entityManager, $notifier);
        $handler(self::command(null));
    }

    public function testThrowsWhenAGivenCategoryDoesNotExist(): void
    {
        $categoryId = new Ulid();

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::once())->method('find')->with($categoryId)->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyChanged');

        $handler = new CreateSubscriptionHandler($categoryRepository, $entityManager, $notifier);

        $this->expectException(\InvalidArgumentException::class);
        $handler(self::command($categoryId));
    }
}
