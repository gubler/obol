<?php

// ABOUTME: Seam for announcing that the subscription set changed, so the obligation series stays current.
// ABOUTME: Command handlers depend on this; the default implementation dispatches a deferred event.

declare(strict_types=1);

namespace App\Service;

interface SubscriptionChangeNotifierInterface
{
    public function notifyChanged(): void;
}
