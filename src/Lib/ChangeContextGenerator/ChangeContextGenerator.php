<?php

// ABOUTME: Generates change context arrays by comparing old and new values for object properties
// ABOUTME: Supports optional formatters to transform values into string representation

declare(strict_types=1);

namespace App\Lib\ChangeContextGenerator;

final readonly class ChangeContextGenerator
{
    /**
     * @param array<int, Change> $changes
     */
    public function __construct(
        private array $changes,
    ) {
    }

    /**
     * @return array<string, array{old: string|int, new: string|int}>
     */
    public function buildContext(): array
    {
        $context = [];

        foreach ($this->changes as $change) {
            if ($change->current !== $change->new) {
                $context[$change->field] = [
                    'old' => $change->current,
                    'new' => $change->new,
                ];
            }
        }

        return $context;
    }
}
