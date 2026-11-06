#!/bin/bash
# UserPromptSubmit: three nudges, never blocking.
#  1. Work order without a done-signal  -> Claude proposes one before coding.
#  2. Decision wording                  -> Claude records it right away.
#  3. Transcript over LEASH_CTX_WARN_KB (default 400), then every LEASH_CTX_STEP_KB (200)
#                                       -> visible reminder to /phase-end and /clear.
set -uo pipefail
payload=$(cat)
proj="${CLAUDE_PROJECT_DIR:-$(printf '%s' "$payload" | jq -r '.cwd // empty')}"
[ -n "$proj" ] && [ -f "$proj/.claude/hooks.off" ] && exit 0

prompt=$(printf '%s' "$payload" | jq -r '.prompt // .prompt_text // empty')
lc=$(printf '%s' "$prompt" | tr '[:upper:]' '[:lower:]')
ctx=""; msg=""

case "$lc" in
  /*) ;;  # skills carry their own protocol
  *)
    if printf '%s' "$lc" | grep -Eq '(^|[^a-zäöü])(implement|add|build|create|fix|write|refactor|make|change|update|mach|bau|implementier|erstell|füg|schreib|änder)[a-zäöü]*([^a-zäöü]|$)' \
       && ! printf '%s' "$lc" | grep -Eq '\?[[:space:]]*$' \
       && ! printf '%s' "$lc" | grep -Eq '(test|make [a-z]|done when|done:|fertig wenn|green|grün|acceptance|exit 0|assert|done\.sh|phpstan|phpunit)'; then
      ctx="This looks like a work order without a done-signal. Before editing files, state the mechanical done-signal (a test, a make target, a command with expected output) or propose one and ask. See CLAUDE.md working rules."
    fi
    if printf '%s' "$lc" | grep -Eq "(we('ll| will) (go with|take|use)|let'?s go with|go with option|we decided|decision:|entschieden|entscheidung:|nehmen wir|wir nehmen)"; then
      ctx="${ctx:+$ctx }The user is making a decision. Record it right away: one line in docs/PROGRESS.md \"Decisions\", or docs/decisions/NNN-*.md if it needs evidence. Update docs/PLAN.md if scope changes."
    fi
  ;;
esac

tp=$(printf '%s' "$payload" | jq -r '.transcript_path // empty')
sid=$(printf '%s' "$payload" | jq -r '.session_id // "nosession"')
if [ -n "$tp" ] && [ -f "$tp" ] && [ -n "$proj" ] && [ -d "$proj/.claude" ]; then
  warn=${LEASH_CTX_WARN_KB:-400}; step=${LEASH_CTX_STEP_KB:-200}
  kb=$(( $(wc -c < "$tp" | tr -d ' ') / 1024 ))
  if [ "$kb" -ge "$warn" ]; then
    bucket=$(( (kb - warn) / step ))
    state="$proj/.claude/.ctx-$sid"
    last=$(cat "$state" 2>/dev/null || echo -1)
    if [ "$bucket" -gt "$last" ]; then
      echo "$bucket" > "$state"
      msg="Context is large (~${kb} KB transcript). Good moment for /phase-end, then /clear — docs/PROGRESS.md carries the handoff."
      ctx="${ctx:+$ctx }The context is large. At the next natural stopping point, suggest /phase-end and /clear to the user."
    fi
  fi
fi

[ -z "$ctx$msg" ] && exit 0
jq -n --arg c "$ctx" --arg m "$msg" '
  (if $m != "" then {systemMessage:$m} else {} end)
  + (if $c != "" then {hookSpecificOutput:{hookEventName:"UserPromptSubmit", additionalContext:$c}} else {} end)'
