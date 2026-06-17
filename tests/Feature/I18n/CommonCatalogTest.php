<?php

// ABOUTME: Asserts the base-layout `common.*` keys resolve through the translator, so the base
// ABOUTME: template renders real copy and not raw ids (ADR-0012 i18n foundation).

declare(strict_types=1);

namespace App\Tests\Feature\I18n;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CommonCatalogTest extends KernelTestCase
{
    #[DataProvider('provideBaseLayoutKeyResolvesCases')]
    public function testBaseLayoutKeyResolves(string $key, string $expected): void
    {
        self::bootKernel();

        $translator = self::getContainer()->get(TranslatorInterface::class);

        self::assertSame($expected, $translator->trans($key, locale: 'en'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideBaseLayoutKeyResolvesCases(): iterable
    {
        yield 'nav: subscriptions' => ['common.nav.subscriptions', 'Subscriptions'];
        yield 'nav: categories' => ['common.nav.categories', 'Categories'];
        yield 'nav: reports' => ['common.nav.reports', 'Reports'];
        yield 'theme toggle' => ['common.theme.toggle', 'Toggle dark mode'];
        yield 'open menu' => ['common.menu.open', 'Open main menu'];
        yield 'default title' => ['common.title.default', 'Welcome!'];
    }
}
