<?php

// ABOUTME: Unit tests for UpdatePaymentSourceHandler verifying name/comment/color updates.
// ABOUTME: Tests that the handler finds the source and calls update; throws on not found.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\PaymentSource;

use App\Entity\PaymentSource;
use App\Enum\TileColor;
use App\Message\Command\PaymentSource\UpdatePaymentSourceCommand;
use App\Message\Command\PaymentSource\UpdatePaymentSourceHandler;
use App\Repository\PaymentSourceRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class UpdatePaymentSourceHandlerTest extends TestCase
{
    public function testHandlerUpdatesNameCommentAndColor(): void
    {
        $source = $this->createMock(PaymentSource::class);
        $source->expects(self::once())
            ->method('update')
            ->with('Updated Name', 'a note', TileColor::Teal)
        ;

        $repository = $this->createMock(PaymentSourceRepository::class);
        $repository->expects(self::once())
            ->method('findForOwner')
            ->willReturn($source)
        ;

        $handler = new UpdatePaymentSourceHandler($repository);
        $handler(new UpdatePaymentSourceCommand(ownerUserId: new Ulid(), paymentSourceId: new Ulid(), name: 'Updated Name', comment: 'a note', color: TileColor::Teal));
    }

    public function testHandlerThrowsWhenPaymentSourceNotFoundForOwner(): void
    {
        $repository = $this->createMock(PaymentSourceRepository::class);
        $repository->expects(self::once())
            ->method('findForOwner')
            ->willReturn(null)
        ;

        $handler = new UpdatePaymentSourceHandler($repository);

        $this->expectException(\InvalidArgumentException::class);
        $handler(new UpdatePaymentSourceCommand(ownerUserId: new Ulid(), paymentSourceId: new Ulid(), name: 'Updated Name', comment: '', color: TileColor::Blue));
    }
}
