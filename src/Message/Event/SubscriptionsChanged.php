<?php

// ABOUTME: Domain event announcing that one user's subscription set changed (created/updated/archived/deleted).
// ABOUTME: Dispatched on event.bus after the change commits; the obligation recorder reacts per owner.

declare(strict_types=1);

namespace App\Message\Event;

use Symfony\Component\Uid\Ulid;

final readonly class SubscriptionsChanged
{
    public function __construct(
        public Ulid $ownerUserId,
    ) {
    }
}
