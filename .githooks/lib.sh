#!/usr/bin/env bash
# ABOUTME: Shared check sprints for the git hooks - fast static checks, then PHPStan, then tests.
# ABOUTME: Sourced by pre-commit (fast sprint only) and pre-push / pre-merge-commit (all three, fail-fast).

# Sprint 1 - the fast static checks. Aggregated (no early return) so one run surfaces every fast
# failure at once. Style runs in check mode, exactly as CI does: a failure means run `mise run cs`
# (or cs:twig) to fix, then re-stage - the hook never silently rewrites the commit.
fast_checks() {
    local status=0
    mise run lint:php      || status=1
    mise run lint:yaml     || status=1
    mise run cs:check      || status=1
    mise run cs:twig:check || status=1
    mise run rector:check  || status=1
    mise run js:cs:check   || status=1
    mise run js:sa         || status=1
    # Host-side, and deliberately in the hooks rather than CI: the CI job runs inside a container
    # with no Docker socket, so this contract is only ever enforced here.
    mise run check:prod-compose || status=1
    # The entrypoint contract needs no Docker, so CI enforces it too; it is here as well so a broken
    # entrypoint fails before the push rather than after.
    mise run check:entrypoint   || status=1
    # Same arrangement for the release scripts, and the same reason with a sharper edge: they run
    # only on a production merge, so without this the first sign of a broken one is a failed release.
    mise run check:release      || status=1
    return "$status"
}

# All four sprints, fail-fast: there is no point running PHPStan or the test suites once a fast
# check has failed, nor the suites once PHPStan has. The JS toolchain (#133) runs host-side via
# npm: lint/types join the fast sprint above, the JS unit tests join the test sprint below.
full_checks() {
    fast_checks       || return 1
    mise run sa       || return 1
    mise run sa:tests || return 1
    mise run test     || return 1
    mise run js:test  || return 1
}
