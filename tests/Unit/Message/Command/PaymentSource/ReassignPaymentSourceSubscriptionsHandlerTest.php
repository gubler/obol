<?php

// ABOUTME: Unit tests for ReassignPaymentSourceSubscriptionsHandler verifying the bulk move.
// ABOUTME: Tests that every subscription on the source is moved and that missing sources throw.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\PaymentSource;

use App\Entity\PaymentSource;
use App\Entity\Subscription;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\PaymentPeriod;
use App\Message\Command\PaymentSource\ReassignPaymentSourceSubscriptionsCommand;
use App\Message\Command\PaymentSource\ReassignPaymentSourceSubscriptionsHandler;
use App\Repository\PaymentSourceRepository;
use App\ValueObject\Money;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ReassignPaymentSourceSubscriptionsHandlerTest extends TestCase
{
    public function testMovesEverySubscriptionFromTheSourceToTheTarget(): void
    {
        $from = new PaymentSource(name: 'Amex 1234');
        $to = new PaymentSource(name: 'Visa 5678');

        $first = $this->subscription($from);
        $second = $this->subscription($from);
        $this->setSubscriptions($from, [$first, $second]);

        $repository = $this->createMock(PaymentSourceRepository::class);
        $repository->expects(self::exactly(2))
            ->method('find')
            ->willReturnCallback(static fn (Ulid $id): ?PaymentSource => match (true) {
                $id->equals($from->id) => $from,
                $id->equals($to->id) => $to,
                default => null,
            })
        ;

        $handler = new ReassignPaymentSourceSubscriptionsHandler($repository);
        $handler(new ReassignPaymentSourceSubscriptionsCommand(fromPaymentSourceId: $from->id, toPaymentSourceId: $to->id));

        self::assertSame($to, $first->paymentSource);
        self::assertSame($to, $second->paymentSource);
        self::assertCount(1, $first->subscriptionEvents);
        self::assertCount(1, $second->subscriptionEvents);
    }

    public function testThrowsWhenTheSourceDoesNotExist(): void
    {
        $repository = $this->createMock(PaymentSourceRepository::class);
        $repository->expects(self::once())->method('find')->willReturn(null);

        $handler = new ReassignPaymentSourceSubscriptionsHandler($repository);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new ReassignPaymentSourceSubscriptionsCommand(fromPaymentSourceId: new Ulid(), toPaymentSourceId: new Ulid()));
    }

    public function testThrowsWhenTheTargetDoesNotExist(): void
    {
        $from = new PaymentSource(name: 'Amex 1234');

        $repository = $this->createMock(PaymentSourceRepository::class);
        $repository->expects(self::exactly(2))
            ->method('find')
            ->willReturnCallback(static fn (Ulid $id): ?PaymentSource => $id->equals($from->id) ? $from : null)
        ;

        $handler = new ReassignPaymentSourceSubscriptionsHandler($repository);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new ReassignPaymentSourceSubscriptionsCommand(fromPaymentSourceId: $from->id, toPaymentSourceId: new Ulid()));
    }

    private function subscription(PaymentSource $paymentSource): Subscription
    {
        return new Subscription(
            owner: new User(email: 'owner@example.com'),
            category: null,
            name: 'Netflix',
            nextRenewal: new \DateTimeImmutable('2024-01-01'),
            paymentPeriod: PaymentPeriod::Month,
            paymentPeriodCount: 1,
            cost: new Money(1500, Currency::USD),
            paymentSource: $paymentSource,
        );
    }

    /**
     * @param array<Subscription> $subscriptions
     */
    private function setSubscriptions(PaymentSource $source, array $subscriptions): void
    {
        // The inverse OneToMany collection is populated by Doctrine in the real path; seed it directly here.
        $reflection = new \ReflectionProperty(PaymentSource::class, 'subscriptions');
        $reflection->setValue($source, new ArrayCollection($subscriptions));
    }
}
