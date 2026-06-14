#!/usr/bin/env bash
# ABOUTME: Shared check sprints for the git hooks - fast static checks, then PHPStan, then tests.
# ABOUTME: Sourced by pre-commit (fast sprint only) and pre-push / pre-merge-commit (all three, fail-fast).

# Sprint 1 - the fast static checks. Aggregated (no early return) so one run surfaces every fast
# failure at once. Style runs in check mode, exactly as CI does: a failure means run `mise run cs`
# (or cs:twig) to fix, then re-stage - the hook never silently rewrites the commit.
fast_checks() {
    local status=0
    mise run lint:php      || status=1
    mise run cs:check      || status=1
    mise run cs:twig:check || status=1
    mise run js:cs:check   || status=1
    mise run js:sa         || status=1
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
