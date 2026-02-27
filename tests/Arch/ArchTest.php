<?php

// ABOUTME: Architecture tests enforced via Pest's arch plugin.
// ABOUTME: Validates structural rules across the codebase.

declare(strict_types=1);

arch('controllers must end with Controller')
    ->expect('App\Controller')
    ->toHaveSuffix('Controller')
;

arch('entities must not depend on controllers')
    ->expect('App\Entity')
    ->not->toUse('App\Controller')
;

arch('no debugging functions in source code')
    ->expect('App')
    ->not->toUse(['dump', 'dd', 'var_dump', 'print_r', 'ray'])
;

arch('enums must be backed')
    ->expect('App\Enum')
    ->toBeEnums()
    ->toHaveSuffix('')
;

arch('repositories must end with Repository')
    ->expect('App\Repository')
    ->toHaveSuffix('Repository')
;
