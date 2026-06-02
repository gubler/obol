#!/usr/bin/env sh
# ABOUTME: PreToolUse guard - blocks editing source code unless a test moved first this session.
# ABOUTME: Enforces "a test was touched first", not "the test was red"; tune the patterns per project.

# Fail open: never block an edit because the guard itself errored (missing jq, bad input, ...).
command -v jq >/dev/null 2>&1 || exit 0

input="$(cat)"
file="$(printf '%s' "$input" | jq -r '.tool_input.file_path // empty' 2>/dev/null)"
session="$(printf '%s' "$input" | jq -r '.session_id // "default"' 2>/dev/null)"
[ -n "$file" ] || exit 0

# Scope the marker by project (a stable hash of the repo path) so different repos
# never share a marker dir - we don't need the project *name*, just a stable key.
state_root="${TDD_GUARD_STATE_DIR:-${TMPDIR:-/tmp}/claude-tdd-guard}"
project="$(printf '%s' "${CLAUDE_PROJECT_DIR:-$PWD}" | cksum | cut -d' ' -f1)"
state_dir="$state_root/$project"
marker="$state_dir/$session"

# A test moved: remember it for the rest of the session, then allow.
if printf '%s' "$file" | grep -Eq '(^|/)(tests?|specs?|__tests__)/|(_test|_spec|\.test|\.spec)\.|(Test|Spec)\.[a-zA-Z]+$|(^|/)test_'; then
    mkdir -p "$state_dir" 2>/dev/null && : > "$marker" 2>/dev/null
    exit 0
fi

# Source code under a recognised source directory, with a code extension.
if printf '%s' "$file" | grep -Eq '(^|/)(src|lib|app|internal|pkg|source)/' \
    && printf '%s' "$file" | grep -Eq '\.(php|py|go|rb|js|jsx|ts|tsx|rs|java|kt|c|h|cpp|hpp|cc|cs|swift|sh)$'; then
    if [ ! -f "$marker" ]; then
        echo "TDD guard: write or update a test before editing $file (no test has moved this session)." >&2
        exit 2
    fi
fi

exit 0
