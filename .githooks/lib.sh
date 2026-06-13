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
    return "$status"
}

# All three sprints, fail-fast: there is no point running PHPStan or the test suite once a fast
# check has failed, nor the suite once PHPStan has. (JS lint/test would slot in as a future sprint.)
full_checks() {
    fast_checks   || return 1
    mise run sa   || return 1
    mise run test || return 1
}
