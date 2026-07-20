<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

// Pin the process timezone to UTC. Calendar dates carry the owner's zone, applied at read time, but
// instant storage (createdAt timestamps) and any stray ambient-zone date path must not vary with the
// host's TZ. Prod deployments should also set date.timezone=UTC; this is the belt-and-braces. See ADR-0021.
date_default_timezone_set('UTC');

return fn(array $context) => new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
