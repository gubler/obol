<?php

// ABOUTME: Represents a single field change with old/new values and optional formatter
// ABOUTME: Used by ChangeContextGenerator to build change context arrays

declare(strict_types=1);

namespace App\Lib\ChangeContextGenerator;

final readonly class Change
{
    public function __construct(
        public string $field,
        public string|int $current,
        public string|int $new,
    ) {
    }
}
