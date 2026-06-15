<?php

// ABOUTME: Thrown when a user-entered money string cannot be parsed into a numeric amount.
// ABOUTME: Caught at the form boundary and surfaced as a field validation error.

declare(strict_types=1);

namespace App\Service\Money\Exception;

final class MoneyParseException extends \RuntimeException
{
}
