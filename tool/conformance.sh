#!/usr/bin/env bash
# Boot Prism mocks of both contracts, run the @group conformance suite against
# them, and tear the mocks down. Propagates PHPUnit's exit code.
set -uo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$here"

runtime_port="${GHAYMA_RUNTIME_PORT:-4010}"
auth_port="${GHAYMA_AUTH_PORT:-4011}"
pids=()

cleanup() {
    for pid in "${pids[@]:-}"; do
        kill "$pid" 2>/dev/null || true
    done
}
trap cleanup EXIT

start_mock() {
    local spec="$1" port="$2"
    npx --yes @stoplight/prism-cli@5 mock "$spec" -p "$port" --errors >"/tmp/prism-${port}.log" 2>&1 &
    pids+=("$!")
}

wait_up() {
    local port="$1" tries=0
    until curl -s -o /dev/null "http://127.0.0.1:${port}/"; do
        tries=$((tries + 1))
        if [ "$tries" -ge 60 ]; then
            echo "Prism on port ${port} did not come up in 60s" >&2
            cat "/tmp/prism-${port}.log" >&2 || true
            return 1
        fi
        sleep 1
    done
}

echo "Starting Prism mocks (runtime :${runtime_port}, auth :${auth_port})…"
start_mock "spec/runtime.v1.yaml" "$runtime_port"
start_mock "spec/auth.v1.yaml" "$auth_port"

wait_up "$runtime_port" || exit 1
wait_up "$auth_port" || exit 1

echo "Running conformance suite…"
GHAYMA_RUNTIME_URL="http://127.0.0.1:${runtime_port}" \
GHAYMA_AUTH_URL="http://127.0.0.1:${auth_port}" \
    vendor/bin/phpunit --testsuite conformance --group conformance
code=$?

exit "$code"
