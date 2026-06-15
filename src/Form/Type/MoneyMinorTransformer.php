<?php

// ABOUTME: Form model transformer between a minor-unit integer amount and a major-unit input string.
// ABOUTME: Parses locale-aware user input (grouping separators, currency symbols) via MoneyParser.

declare(strict_types=1);

namespace App\Form\Type;

use App\Service\Money\Exception\MoneyParseException;
use App\Service\Money\MoneyParser;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @implements DataTransformerInterface<int, string>
 */
final readonly class MoneyMinorTransformer implements DataTransformerInterface
{
    public function __construct(
        private MoneyParser $parser,
        private int $fractionDigits,
    ) {
    }

    public function transform(mixed $value): string
    {
        // Treat an unset or zero amount as an empty field rather than rendering "0.00".
        if (null === $value || 0 === $value) {
            return '';
        }

        return $this->parser->toMajorString((int) $value, $this->fractionDigits);
    }

    public function reverseTransform(mixed $value): int
    {
        if (null === $value || '' === trim((string) $value)) {
            // Blank maps to 0, which the GreaterThanOrEqual(1) constraint rejects with a clear message.
            return 0;
        }

        try {
            return $this->parser->toMinor((string) $value, $this->fractionDigits);
        } catch (MoneyParseException $exception) {
            throw new TransformationFailedException($exception->getMessage(), previous: $exception);
        }
    }
}
