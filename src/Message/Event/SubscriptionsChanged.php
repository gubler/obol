<?php

// ABOUTME: Domain event announcing that the subscription set changed (created/updated/archived/deleted).
// ABOUTME: Dispatched on event.bus after the change commits; the obligation recorder reacts. No payload needed.

declare(strict_types=1);

namespace App\Message\Event;

final readonly class SubscriptionsChanged
{
}
