<?php

// ABOUTME: Unit tests for UpdateSubscriptionHandler verifying subscription updates.
// ABOUTME: Tests happy path, subscription not found, and category not found branches.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Subscription;

use App\Entity\Category;
use App\Entity\Subscription;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\Message\Command\Subscription\UpdateSubscriptionCommand;
use App\Message\Command\Subscription\UpdateSubscriptionHandler;
use App\Repository\CategoryRepository;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class UpdateSubscriptionHandlerTest extends TestCase
{
    public function testHandlerUpdatesSubscription(): void
    {
        $subscriptionUlid = new Ulid();
        $categoryUlid = new Ulid();
        $nextRenewal = new \DateTimeImmutable('2025-01-15');

        $subscription = $this->createMock(Subscription::class);
        $subscription->expects(self::once())->method('update');
        $subscription->expects(self::never())->method('automatePayments');

        $category = self::createStub(Category::class);

        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())
            ->method('find')
            ->willReturn($subscription)
        ;

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::once())
            ->method('find')
            ->willReturn($category)
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $notifier);
        $handler(new UpdateSubscriptionCommand(
            subscriptionId: $subscriptionUlid,
            categoryId: $categoryUlid,
            name: 'Netflix Premium',
            nextRenewal: $nextRenewal,
            description: 'Premium plan',
            link: 'https://netflix.com',
            logo: 'logo.png',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1999,
            currency: Currency::USD,
            color: TileColor::Blue,
        ));
    }

    public function testHandlerResumesAutomatedGenerationWhenRestartIsRequested(): void
    {
        $subscriptionUlid = new Ulid();
        $categoryUlid = new Ulid();
        $nextRenewal = new \DateTimeImmutable('2025-03-01');

        $subscription = $this->createMock(Subscription::class);
        $subscription->expects(self::once())->method('update');
        $subscription->expects(self::once())->method('automatePayments')->with($nextRenewal);

        $category = self::createStub(Category::class);

        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())->method('find')->willReturn($subscription);

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::once())->method('find')->willReturn($category);

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $notifier);
        $handler(new UpdateSubscriptionCommand(
            subscriptionId: $subscriptionUlid,
            categoryId: $categoryUlid,
            name: 'Netflix Premium',
            nextRenewal: $nextRenewal,
            description: 'Premium plan',
            link: 'https://netflix.com',
            logo: 'logo.png',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1999,
            currency: Currency::USD,
            color: TileColor::Blue,
            restartPaymentGeneration: true,
        ));
    }

    public function testHandlerThrowsWhenSubscriptionNotFound(): void
    {
        $subscriptionUlid = new Ulid();
        $categoryUlid = new Ulid();

        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())
            ->method('find')
            ->willReturn(null)
        ;

        $categoryRepository = self::createStub(CategoryRepository::class);
        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyChanged');

        $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $notifier);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new UpdateSubscriptionCommand(
            subscriptionId: $subscriptionUlid,
            categoryId: $categoryUlid,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable(),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
            currency: Currency::USD,
            color: TileColor::Blue,
        ));
    }

    public function testHandlerThrowsWhenCategoryNotFound(): void
    {
        $subscriptionUlid = new Ulid();
        $categoryUlid = new Ulid();

        $subscription = self::createStub(Subscription::class);

        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())
            ->method('find')
            ->willReturn($subscription)
        ;

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::once())
            ->method('find')
            ->willReturn(null)
        ;

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyChanged');

        $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $notifier);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new UpdateSubscriptionCommand(
            subscriptionId: $subscriptionUlid,
            categoryId: $categoryUlid,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable(),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1500,
            currency: Currency::USD,
            color: TileColor::Blue,
        ));
    }
}
