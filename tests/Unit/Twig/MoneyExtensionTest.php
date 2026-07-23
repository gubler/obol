<?php

// ABOUTME: Unit test for the money Twig filter - delegates to MoneyFormatter for the ambient locale.
// ABOUTME: Proves the filter is wired to the formatter; the formatting itself is covered in MoneyFormatterTest.

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Enum\Currency;
use App\Service\Money\MoneyFormatter;
use App\Twig\MoneyExtension;
use App\ValueObject\Money;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\LocaleSwitcher;

final class MoneyExtensionTest extends TestCase
{
    public function testTheMoneyFilterRendersTheAmountForTheAmbientLocale(): void
    {
        $extension = new MoneyExtension(new MoneyFormatter(new LocaleSwitcher('en', [])));

        self::assertSame('$15.99', $extension->format(new Money(1599, Currency::USD)));
    }
}
