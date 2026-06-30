<?php

// ABOUTME: Unit tests for CreatePaymentSourceHandler verifying creation with comment and color.
// ABOUTME: Mocks the entity manager and asserts the persisted source carries the chosen name/comment/color.

declare(strict_types=1);

namespace App\Tests\Unit\Message\Command\PaymentSource;

use App\Entity\PaymentSource;
use App\Enum\TileColor;
use App\Message\Command\PaymentSource\CreatePaymentSourceCommand;
use App\Message\Command\PaymentSource\CreatePaymentSourceHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CreatePaymentSourceHandlerTest extends TestCase
{
    public function testPersistsAPaymentSourceWithTheChosenNameCommentAndColor(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')
            ->with(self::callback(static fn (PaymentSource $source): bool => 'Amex 1234' === $source->name
                && 'reissued' === $source->comment
                && TileColor::Violet === $source->color))
        ;

        $handler = new CreatePaymentSourceHandler($entityManager);
        $handler(new CreatePaymentSourceCommand(name: 'Amex 1234', comment: 'reissued', color: TileColor::Violet));
    }
}
