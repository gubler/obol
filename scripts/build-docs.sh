#!/usr/bin/env bash
# ABOUTME: Builds the MkDocs documentation site for Obol.
# ABOUTME: Copies manifest.json into the built site directory for Ook integration.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

cd "${REPO_ROOT}"

mkdocs build

cp docs/manifest.json site/manifest.json

echo "Site built in ${REPO_ROOT}/site/"
