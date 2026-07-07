<?php

// ABOUTME: Asserts the en-GB and en-CA partial catalogs override the diverging spellings and fall
// ABOUTME: back to the complete `en` base for every other key (ADR-0012 minimal-variant catalogs).

declare(strict_types=1);

namespace App\Tests\Feature\I18n;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class RegionalCatalogTest extends KernelTestCase
{
    #[DataProvider('provideRegionalKeyResolvesCases')]
    public function testRegionalKeyResolves(string $locale, string $key, string $expected): void
    {
        self::bootKernel();

        $translator = self::getContainer()->get(TranslatorInterface::class);

        self::assertSame($expected, $translator->trans($key, locale: $locale));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideRegionalKeyResolvesCases(): iterable
    {
        // en-GB overrides both the -our and the -ise spellings.
        yield 'en-GB subscription colour' => ['en-GB', 'subscription.form.color', 'Colour'];
        yield 'en-GB category colour' => ['en-GB', 'category.form.color', 'Colour'];
        yield 'en-GB payment source colour' => ['en-GB', 'payment_source.form.color', 'Colour'];
        yield 'en-GB uncategorised' => ['en-GB', 'subscription.group.uncategorized', 'Uncategorised'];
        yield 'en-GB grey swatch' => ['en-GB', 'enum.tile_color.grey', 'Grey'];
        // Anything the variant does not override resolves through the en base.
        yield 'en-GB falls back for save' => ['en-GB', 'common.action.save', 'Save'];

        // en-CA takes the -our spelling but keeps the -ize/-ized ending.
        yield 'en-CA subscription colour' => ['en-CA', 'subscription.form.color', 'Colour'];
        yield 'en-CA category colour' => ['en-CA', 'category.form.color', 'Colour'];
        yield 'en-CA payment source colour' => ['en-CA', 'payment_source.form.color', 'Colour'];
        yield 'en-CA keeps uncategorized' => ['en-CA', 'subscription.group.uncategorized', 'Uncategorized'];
        yield 'en-CA grey swatch' => ['en-CA', 'enum.tile_color.grey', 'Grey'];
        yield 'en-CA falls back for save' => ['en-CA', 'common.action.save', 'Save'];
    }
}
