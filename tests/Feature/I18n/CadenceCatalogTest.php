<?php

// ABOUTME: Asserts the ICU `subscription.cadence` message pluralizes the billing period correctly.
// ABOUTME: Replaces the old "~ 's'" Twig hack with a catalog-owned plural (ADR-0012).

declare(strict_types=1);

namespace App\Tests\Feature\I18n;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CadenceCatalogTest extends KernelTestCase
{
    #[DataProvider('provideCadenceRendersCountAndPeriodCases')]
    public function testCadenceRendersCountAndPeriod(int $count, string $period, string $expected): void
    {
        self::bootKernel();

        $translator = self::getContainer()->get(TranslatorInterface::class);

        self::assertSame(
            $expected,
            $translator->trans('subscription.cadence', ['count' => $count, 'period' => $period], locale: 'en'),
        );
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function provideCadenceRendersCountAndPeriodCases(): iterable
    {
        yield 'singular month' => [1, 'month', 'month'];
        yield 'plural months' => [3, 'month', '3 months'];
        yield 'singular year' => [1, 'year', 'year'];
        yield 'plural weeks' => [2, 'week', '2 weeks'];
    }
}
