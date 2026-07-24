<?php

// ABOUTME: Query message for the admin Overview's at-a-glance system metrics; carries no parameters.
// ABOUTME: Deliberately cross-owner (admin-only), so it is not owner-scoped and carries no ownerUserId.

declare(strict_types=1);

namespace App\Message\Query\Admin;

final readonly class GetAdminOverviewQuery
{
}
