#!/bin/bash
# PostToolUse(Bash): after a "Phase end:" commit, remind the user to /clear and show the next prompt.
set -uo pipefail
payload=$(cat)
proj="${CLAUDE_PROJECT_DIR:-$(printf '%s' "$payload" | jq -r '.cwd // empty')}"
[ -n "$proj" ] || exit 0
[ -f "$proj/.claude/hooks.off" ] && exit 0
cmd=$(printf '%s' "$payload" | jq -r '.tool_input.command // empty')
printf '%s' "$cmd" | grep -Eq 'git([[:space:]]+-C[[:space:]]+[^[:space:]]+)?[[:space:]]+commit' || exit 0
cd "$proj" || exit 0
subject=$(git log -1 --format=%s 2>/dev/null)
case "$subject" in "Phase end:"*) ;; *) exit 0 ;; esac
next=$(awk '$0 == "## Next prompt" {on=1; next} on && /^## / {exit} on && NF && !/^<!--/ {print; exit}' docs/PROGRESS.md 2>/dev/null)
jq -n --arg n "${next:-see docs/PROGRESS.md}" '{systemMessage:("Phase boundary. Run /clear, then: " + $n)}'
