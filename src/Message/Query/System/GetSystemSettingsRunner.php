<?php

// ABOUTME: Runner for GetSystemSettingsQuery - returns the SystemSettings singleton via the repository.
// ABOUTME: The single read seam for system settings; caching, if ever needed, decorates here (ADR-0020).

declare(strict_types=1);

namespace App\Message\Query\System;

use App\Entity\SystemSettings;
use App\Repository\SystemSettingsRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus', handles: GetSystemSettingsQuery::class)]
final readonly class GetSystemSettingsRunner
{
    public function __construct(
        private SystemSettingsRepository $systemSettingsRepository,
    ) {
    }

    public function __invoke(GetSystemSettingsQuery $query): SystemSettings
    {
        return $this->systemSettingsRepository->get();
    }
}
