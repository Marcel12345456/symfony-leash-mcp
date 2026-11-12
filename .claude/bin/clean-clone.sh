#!/bin/sh
# Clean-clone test (PLAN M8, README "5-minute rule"): clone the pushed branch into a fresh directory, set it up
# the way the README says, replay the whole demo with no model, and time it. Exit 1 on any failure or above the
# budget. Uses Docker only, like a newcomer; the composer cache volume is new (the compose project is new).
#   clean-clone.sh [branch] [budget-seconds]     defaults: current branch, 300
set -eu
repo="$(cd "$(dirname "$0")/../.." && pwd)"
branch="${1:-$(git -C "$repo" branch --show-current)}"
budget="${2:-300}"
url="$(git -C "$repo" remote get-url origin)"
dir="$(mktemp -d "${TMPDIR:-/tmp}/leash-clean-clone.XXXXXX")"
project="leashclean$$"
cleanup() {
    (cd "$dir/leash" 2>/dev/null && COMPOSE_PROJECT_NAME="$project" docker compose down -v --remove-orphans >/dev/null 2>&1) || true
    rm -rf "$dir"
}
trap cleanup EXIT INT TERM

start=$(date +%s)
step() { printf '[%4ss] %s\n' "$(( $(date +%s) - start ))" "$1"; }

step "clone $branch from $url"
git clone -q --depth 1 --branch "$branch" "$url" "$dir/leash"
cd "$dir/leash"
export COMPOSE_PROJECT_NAME="$project"
# A port nobody uses, so a running `make demo` does not collide.
export LEASH_PORT=18765

step "docker compose build php"
docker compose --progress quiet build php
step "make install"
make install >/dev/null
step "replay the demo (no model)"
out="$(docker compose --progress quiet run --rm --no-deps php bin/console leash:demo:prepare --model=replay 2>&1)" || {
    printf '%s\n' "$out" | tail -20
    step "FAIL: leash:demo:prepare"
    exit 1
}
printf '%s\n' "$out" | grep -E 'Result:' || { step "FAIL: no Werewolf result"; exit 1; }

took=$(( $(date +%s) - start ))
if [ "$took" -gt "$budget" ]; then
    step "FAIL: took ${took}s, budget ${budget}s"
    exit 1
fi
step "OK: clean clone to replayed demo in ${took}s (budget ${budget}s)"
