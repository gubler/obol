<?php

// ABOUTME: Base for the domain errors raised while managing secondary addresses (verify / promote / remove).
// ABOUTME: Each carries the translation key a controller flashes, so the mapping lives with the rule it names.

declare(strict_types=1);

namespace App\Exception;

abstract class AbstractSecondaryEmailException extends \DomainException
{
    abstract public function translationKey(): string;
}
