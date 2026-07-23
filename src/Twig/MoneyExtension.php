<?php

// ABOUTME: Twig `money` filter rendering a Money in the currency's convention for the ambient locale.
// ABOUTME: Delegates to MoneyFormatter, keeping call sites as {{ amount|money }} with no locale threading.

declare(strict_types=1);

namespace App\Twig;

use App\Service\Money\MoneyFormatter;
use App\ValueObject\Money;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class MoneyExtension extends AbstractExtension
{
    public function __construct(
        private readonly MoneyFormatter $moneyFormatter,
    ) {
    }

    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('money', $this->format(...)),
        ];
    }

    public function format(Money $money): string
    {
        return $this->moneyFormatter->format($money);
    }
}
