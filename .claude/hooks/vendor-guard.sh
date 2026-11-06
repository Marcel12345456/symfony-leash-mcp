#!/bin/bash
# PreToolUse(Read|Grep|Glob|Bash): the main session does not read vendor/.
# Library research goes through /verify -> verify-researcher (a subagent, allowed).
# Running tools from vendor/bin is allowed. LEASH_VENDOR_GUARD=warn downgrades deny to a note.
set -uo pipefail
payload=$(cat)
proj="${CLAUDE_PROJECT_DIR:-$(printf '%s' "$payload" | jq -r '.cwd // empty')}"
[ -n "$proj" ] && [ -f "$proj/.claude/hooks.off" ] && exit 0

agent=$(printf '%s' "$payload" | jq -r '.agent_id // empty')
[ -n "$agent" ] && exit 0   # subagent: allowed

tool=$(printf '%s' "$payload" | jq -r '.tool_name // empty')
hit=0
case "$tool" in
  Read|Grep|Glob)
    target=$(printf '%s' "$payload" | jq -r '[.tool_input.file_path, .tool_input.path, .tool_input.pattern, .tool_input.glob] | map(select(. != null)) | join(" ")')
    printf '%s' "$target" | grep -Eq '(^|/|[[:space:]])vendor(/|$)' && hit=1 ;;
  Bash)
    cmd=$(printf '%s' "$payload" | jq -r '.tool_input.command // empty')
    # Ignore heredoc bodies and single-quoted strings (file content, jq programs, test fixtures),
    # and running tools from vendor/bin.
    stripped=$(printf '%s' "$cmd" | awk '{ i = index($0, "<<"); if (i) { print substr($0, 1, i - 1); exit } print }' \
      | sed -E "s/'[^']*'//g; s#(^|[^[:alnum:]_])vendor/bin/#\1VBIN/#g")
    if printf '%s' "$stripped" | grep -Eq '(^|[;&|(]|[[:space:]])(cat|head|tail|less|more|sed|awk|grep|rg|find|ls|tree|wc)([[:space:]])' \
       && printf '%s' "$stripped" | grep -Eq '(^|[^[:alnum:]_])vendor(/|[[:space:]]|$)'; then hit=1; fi ;;
esac
[ "$hit" -eq 1 ] || exit 0

reason="Reading vendor/ in the main session is blocked to keep the context small. Use /verify <question> (delegates to the verify-researcher subagent and writes docs/decisions/NNN-*.md)."
if [ "${LEASH_VENDOR_GUARD:-deny}" = "warn" ]; then
  jq -n --arg r "$reason" '{hookSpecificOutput:{hookEventName:"PreToolUse", additionalContext:$r}}'
else
  jq -n --arg r "$reason" '{hookSpecificOutput:{hookEventName:"PreToolUse", permissionDecision:"deny", permissionDecisionReason:$r}}'
fi
