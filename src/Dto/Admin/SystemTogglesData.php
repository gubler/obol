<?php

// ABOUTME: Data Transfer Object for the admin System Toggles form (the runtime system settings).
// ABOUTME: The controller pre-fills it from the SystemSettings singleton and carries it to the toggle commands.

declare(strict_types=1);

namespace App\Dto\Admin;

final class SystemTogglesData
{
    public bool $publicSignupEnabled = false;
}
