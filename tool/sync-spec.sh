#!/usr/bin/env bash
# Refresh the vendored OpenAPI contracts from the served documents.
# CI runs this, then `git diff --exit-code spec/` to fail on a stale copy.
set -euo pipefail

runtime_url="${GHAYMA_RUNTIME_SPEC_URL:-https://api.ghayma.tech/openapi.yaml}"
auth_url="${GHAYMA_AUTH_SPEC_URL:-https://auth.ghayma.tech/openapi.yaml}"

here="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "Fetching runtime contract from ${runtime_url}"
curl -fsSL "${runtime_url}" -o "${here}/spec/runtime.v1.yaml"

echo "Fetching auth contract from ${auth_url}"
curl -fsSL "${auth_url}" -o "${here}/spec/auth.v1.yaml"

echo "Done."
