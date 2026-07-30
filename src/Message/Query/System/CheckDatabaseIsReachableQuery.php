<?php

// ABOUTME: Query message asking whether the application's database answers a round trip.
// ABOUTME: Dispatched via query.bus and handled by CheckDatabaseIsReachableRunner.

declare(strict_types=1);

namespace App\Message\Query\System;

final readonly class CheckDatabaseIsReachableQuery
{
}
