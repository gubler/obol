<?php

// ABOUTME: Query message for the app-global system settings singleton.
// ABOUTME: Dispatched via query.bus and handled by GetSystemSettingsRunner; carries no parameters.

declare(strict_types=1);

namespace App\Message\Query\System;

final readonly class GetSystemSettingsQuery
{
}
