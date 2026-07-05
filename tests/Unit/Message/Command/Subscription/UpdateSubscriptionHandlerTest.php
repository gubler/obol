<?php

// ABOUTME: Unit tests for UpdateSubscriptionHandler verifying subscription updates.
// ABOUTME: Tests happy path, not-found branches, and category/payment-source resolution.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\Subscription;

use App\Entity\Category;
use App\Entity\PaymentSource;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Enum\TileColor;
use App\Message\Command\Subscription\UpdateSubscriptionCommand;
use App\Message\Command\Subscription\UpdateSubscriptionHandler;
use App\Repository\CategoryRepository;
use App\Repository\PaymentSourceRepository;
use App\Repository\SubscriptionRepository;
use App\Service\SubscriptionChangeNotifierInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
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
            ->method('findForOwner')
            ->willReturn($subscription)
        ;

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::once())
            ->method('findForOwner')
            ->willReturn($category)
        ;

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::never())->method('findForOwner');

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $paymentSourceRepository, $notifier, new MockClock());
        $handler(new UpdateSubscriptionCommand(
            ownerUserId: new Ulid(),
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

    public function testHandlerUpdatesToAnUncategorizedSubscriptionWhenCategoryIdIsNull(): void
    {
        $subscription = $this->createMock(Subscription::class);
        $subscription->expects(self::once())->method('update');

        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())->method('findForOwner')->willReturn($subscription);

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::never())->method('findForOwner');

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::never())->method('findForOwner');

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $paymentSourceRepository, $notifier, new MockClock());
        $handler(new UpdateSubscriptionCommand(
            ownerUserId: new Ulid(),
            subscriptionId: new Ulid(),
            categoryId: null,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2025-01-15'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1999,
            currency: Currency::USD,
            color: TileColor::Blue,
        ));
    }

    public function testHandlerResolvesTheGivenPaymentSource(): void
    {
        $source = new PaymentSource(owner: new User(email: 'owner@example.com'), name: 'Amex 1234');

        $subscription = $this->createMock(Subscription::class);
        $subscription->expects(self::once())->method('update')
            ->with(self::anything(), self::anything(), self::anything(), self::anything(), self::anything(), self::anything(), self::anything(), self::anything(), self::anything(), self::anything(), $source)
        ;

        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())->method('findForOwner')->willReturn($subscription);

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::never())->method('findForOwner');

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::once())->method('findForOwner')->with($source->id, self::anything())->willReturn($source);

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $paymentSourceRepository, $notifier, new MockClock());
        $handler(new UpdateSubscriptionCommand(
            ownerUserId: new Ulid(),
            subscriptionId: new Ulid(),
            categoryId: null,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2025-01-15'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1999,
            currency: Currency::USD,
            color: TileColor::Blue,
            paymentSourceId: $source->id,
        ));
    }

    public function testHandlerThrowsWhenAGivenPaymentSourceDoesNotExist(): void
    {
        $paymentSourceId = new Ulid();

        $subscription = self::createStub(Subscription::class);

        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())->method('findForOwner')->willReturn($subscription);

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::never())->method('findForOwner');

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::once())->method('findForOwner')->with($paymentSourceId, self::anything())->willReturn(null);

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyChanged');

        $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $paymentSourceRepository, $notifier, new MockClock());

        $this->expectException(\InvalidArgumentException::class);
        $handler(new UpdateSubscriptionCommand(
            ownerUserId: new Ulid(),
            subscriptionId: new Ulid(),
            categoryId: null,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2025-01-15'),
            description: '',
            link: '',
            logo: '',
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: 1999,
            currency: Currency::USD,
            color: TileColor::Blue,
            paymentSourceId: $paymentSourceId,
        ));
    }

    public function testHandlerResumesAutomatedGenerationWhenRestartIsRequested(): void
    {
        $subscriptionUlid = new Ulid();
        $categoryUlid = new Ulid();
        $nextRenewal = new \DateTimeImmutable('2025-03-01');

        $subscription = $this->createMock(Subscription::class);
        $subscription->expects(self::once())->method('update');
        // The handler passes its clock's current instant as the "now" automatePayments judges against.
        $subscription->expects(self::once())->method('automatePayments')->with($nextRenewal, self::isInstanceOf(\DateTimeImmutable::class));

        $category = self::createStub(Category::class);

        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())->method('findForOwner')->willReturn($subscription);

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::once())->method('findForOwner')->willReturn($category);

        $paymentSourceRepository = $this->createMock(PaymentSourceRepository::class);
        $paymentSourceRepository->expects(self::never())->method('findForOwner');

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::once())->method('notifyChanged');

        $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $paymentSourceRepository, $notifier, new MockClock());
        $handler(new UpdateSubscriptionCommand(
            ownerUserId: new Ulid(),
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
        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())
            ->method('findForOwner')
            ->willReturn(null)
        ;

        $categoryRepository = self::createStub(CategoryRepository::class);
        $paymentSourceRepository = self::createStub(PaymentSourceRepository::class);
        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyChanged');

        $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $paymentSourceRepository, $notifier, new MockClock());

        $this->expectException(\InvalidArgumentException::class);
        $handler(new UpdateSubscriptionCommand(
            ownerUserId: new Ulid(),
            subscriptionId: new Ulid(),
            categoryId: new Ulid(),
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
        $subscription = self::createStub(Subscription::class);

        $subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $subscriptionRepository->expects(self::once())
            ->method('findForOwner')
            ->willReturn($subscription)
        ;

        $categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryRepository->expects(self::once())
            ->method('findForOwner')
            ->willReturn(null)
        ;

        $paymentSourceRepository = self::createStub(PaymentSourceRepository::class);

        $notifier = $this->createMock(SubscriptionChangeNotifierInterface::class);
        $notifier->expects(self::never())->method('notifyChanged');

        $handler = new UpdateSubscriptionHandler($subscriptionRepository, $categoryRepository, $paymentSourceRepository, $notifier, new MockClock());

        $this->expectException(\InvalidArgumentException::class);
        $handler(new UpdateSubscriptionCommand(
            ownerUserId: new Ulid(),
            subscriptionId: new Ulid(),
            categoryId: new Ulid(),
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
