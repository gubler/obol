<?php

// ABOUTME: Unit tests for the PaymentSource entity ensuring proper instantiation and state validation.
// ABOUTME: Tests verify valid creation, property initialization, the comment field, and business invariants.

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\PaymentSource;
use App\Enum\TileColor;
use PHPUnit\Framework\TestCase;

final class PaymentSourceTest extends TestCase
{
    public function testCreatesPaymentSourceWithValidName(): void
    {
        $source = new PaymentSource(name: 'Amex 1234');

        self::assertSame('Amex 1234', $source->name);
    }

    public function testCreatesPaymentSourceWithAChosenColorAndComment(): void
    {
        $source = new PaymentSource(name: 'Visa', comment: 'Expires 09/27', color: TileColor::Violet);

        self::assertSame('Expires 09/27', $source->comment);
        self::assertSame(TileColor::Violet, $source->color);
    }

    public function testDefaultsToAnEmptyCommentAndARandomColor(): void
    {
        $source = new PaymentSource(name: 'Checking');

        self::assertSame('', $source->comment);
        self::assertContains($source->color, TileColor::cases());
    }

    public function testTrimsName(): void
    {
        $source = new PaymentSource(name: '  PayPal  ');

        self::assertSame('PayPal', $source->name);
    }

    public function testUpdatesNameCommentAndColor(): void
    {
        $source = new PaymentSource(name: 'Original', comment: 'old', color: TileColor::Red);

        $source->update(name: '  Amex 5678  ', comment: 'reissued', color: TileColor::Teal);

        self::assertSame('Amex 5678', $source->name);
        self::assertSame('reissued', $source->comment);
        self::assertSame(TileColor::Teal, $source->color);
    }

    public function testInitializesEmptySubscriptionsCollection(): void
    {
        $source = new PaymentSource(name: 'Amex');

        self::assertCount(0, $source->subscriptions);
    }

    public function testUpdateChangesOnlyTheProvidedFields(): void
    {
        $source = new PaymentSource(name: 'Original', comment: 'old', color: TileColor::Red);

        $source->update(comment: 'just the comment');

        self::assertSame('Original', $source->name);
        self::assertSame('just the comment', $source->comment);
        self::assertSame(TileColor::Red, $source->color);
    }

    public function testUpdateTrimsTheNameWhenProvided(): void
    {
        $source = new PaymentSource(name: 'Original');

        $source->update(name: '  Updated  ');

        self::assertSame('Updated', $source->name);
    }

    public function testUpdateRejectsWhenNoFieldIsProvided(): void
    {
        $source = new PaymentSource(name: 'Valid');

        $this->expectException(\Assert\InvalidArgumentException::class);

        $source->update();
    }

    public function testUpdateRejectsAnEmptyName(): void
    {
        $source = new PaymentSource(name: 'Valid');

        $this->expectException(\Assert\InvalidArgumentException::class);

        $source->update(name: '');
    }

    public function testRejectsEmptyName(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new PaymentSource(name: '');
    }

    public function testRejectsWhitespaceName(): void
    {
        $this->expectException(\Assert\InvalidArgumentException::class);

        new PaymentSource(name: '   ');
    }
}
