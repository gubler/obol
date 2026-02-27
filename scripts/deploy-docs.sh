#!/usr/bin/env bash
# ABOUTME: Builds and deploys the Obol documentation to the homelab docs server.
# ABOUTME: Deploys to hex:/srv/docs/obol/ for serving via Caddy and the Ook hub.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SITE_NAME="${1:-obol}"

bash "${SCRIPT_DIR}/build-docs.sh"

echo "Syncing to hex:/srv/docs/${SITE_NAME}/..."
ssh hex "mkdir -p /srv/docs/${SITE_NAME}"
rsync -avz --delete "${SCRIPT_DIR}/../site/" "hex:/srv/docs/${SITE_NAME}/"

echo "Deployed to docs.dev88.work/${SITE_NAME}/"
echo "Run ook/scripts/deploy.sh to update the Ook hub index."
