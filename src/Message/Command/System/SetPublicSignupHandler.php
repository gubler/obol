<?php

// ABOUTME: Handler for SetPublicSignupCommand - loads the settings singleton, flips the flag, and flushes.

declare(strict_types=1);

namespace App\Message\Command\System;

use App\Repository\SystemSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus', handles: SetPublicSignupCommand::class)]
final readonly class SetPublicSignupHandler
{
    public function __construct(
        private SystemSettingsRepository $systemSettingsRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SetPublicSignupCommand $command): void
    {
        $settings = $this->systemSettingsRepository->get();
        $settings->changePublicSignup($command->enabled);

        $this->entityManager->flush();
    }
}
