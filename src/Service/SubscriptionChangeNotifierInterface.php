<?php

// ABOUTME: Seam for announcing that a user's subscription set changed, so their obligation series stays current.
// ABOUTME: Command handlers depend on this; the default implementation dispatches a deferred event.

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Uid\Ulid;

interface SubscriptionChangeNotifierInterface
{
    public function notifyChanged(Ulid $ownerUserId): void;
}
