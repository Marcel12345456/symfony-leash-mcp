#!/bin/bash
# SessionStart (startup|clear|resume|compact): inject the handoff instead of a whole-repo tour.
# Output: PROGRESS "Current" + "Next prompt", branch, dirty files, last commits. Max 2000 chars.
set -uo pipefail
cat >/dev/null
proj="${CLAUDE_PROJECT_DIR:-$(pwd)}"
cd "$proj" || exit 0
[ -f .claude/hooks.off ] && exit 0

section() { # print the body of "## $1" from PROGRESS.md, comments stripped
  awk -v h="## $1" '$0 == h {on=1; next} on && /^## / {exit} on' docs/PROGRESS.md 2>/dev/null \
    | sed '/^<!--/,/-->$/d' | sed '/^[[:space:]]*$/d'
}

branch=$(git branch --show-current 2>/dev/null)
dirty=$(git status -s 2>/dev/null | head -10)
commits=$(git log --oneline -3 2>/dev/null)

ctx=$(cat <<TXT
Leash session handoff (from docs/PROGRESS.md, injected by .claude/hooks/session-start.sh).

Current:
$(section Current)

Next prompt: $(section "Next prompt" | head -3)

Branch: ${branch:-none}
Uncommitted:
${dirty:-  (clean)}
Last commits:
${commits:-  (none)}

Protocol: do not read docs/PLAN.md in full. Load one section with .claude/bin/plan-section.sh <0|M1|3.2|…>. Start plan work with /phase <key>, research vendor/ only via /verify, close with /phase-end.
TXT
)
ctx=$(printf '%s' "$ctx" | head -c 2000)
jq -n --arg c "$ctx" '{hookSpecificOutput:{hookEventName:"SessionStart", additionalContext:$c}}'
