# Sourced by run.sh. Tests for .claude/hooks/*.sh, fed with sample hook payloads.
H=.claude/hooks
hp() { # prompt -> UserPromptSubmit payload (no transcript)
  jq -n --arg p "$1" --arg cwd "$HP_PROJ" '{session_id:"t", prompt:$p, cwd:$cwd, hook_event_name:"UserPromptSubmit"}'
}
HP_PROJ="$TMP/proj"; mkdir -p "$HP_PROJ/.claude"
run_prompt() { hp "$1" | CLAUDE_PROJECT_DIR="$HP_PROJ" bash $H/prompt-check.sh; }

# ---- prompt-check: work order without done-signal
for p in "implement the SchemaFactory" "add a voter for refunds" "fix the inbox form" \
         "mach den Telegram-Kanal fertig" "create the seed command for C1 to C8"; do
  expect_out "prompt-check flags: $p" 'done-signal' "$(run_prompt "$p")"
done
for p in "implement SchemaFactory, done when SchemaFactoryTest is green" "what does ActorSelector do?" \
         "/phase M1" "add the voter; make qa must pass" "how do we deal with float money here"; do
  expect_no_out "prompt-check quiet: $p" 'done-signal' "$(run_prompt "$p")"
done
# ---- prompt-check: decisions
expect_out    "prompt-check decision: en" 'Record it' "$(run_prompt "we'll go with path B for MRTR")"
expect_out    "prompt-check decision: de" 'Record it' "$(run_prompt "ok, entschieden: Werewolf bleibt drin")"
expect_no_out "prompt-check no decision"  'Record it' "$(run_prompt "what are the options for MRTR?")"
# ---- prompt-check: context size, once per step
tp="$TMP/transcript.jsonl"; head -c 450000 /dev/zero > "$tp"
ctxp() { jq -n --arg tp "$tp" --arg cwd "$HP_PROJ" '{session_id:"ctx", prompt:"hello", transcript_path:$tp, cwd:$cwd}' \
          | CLAUDE_PROJECT_DIR="$HP_PROJ" bash $H/prompt-check.sh; }
expect_out    "ctx: first warning at 440 KB" '"systemMessage".*/clear' "$(ctxp)"
expect_no_out "ctx: no repeat in same step"  'systemMessage' "$(ctxp)"
head -c 700000 /dev/zero > "$tp"
expect_out    "ctx: warns again next step"   'systemMessage' "$(ctxp)"
head -c 100000 /dev/zero > "$TMP/small.jsonl"
expect_no_out "ctx: small transcript quiet" 'systemMessage' "$(jq -n --arg tp "$TMP/small.jsonl" --arg cwd "$HP_PROJ" '{session_id:"s2",prompt:"hi",transcript_path:$tp,cwd:$cwd}' | CLAUDE_PROJECT_DIR="$HP_PROJ" bash $H/prompt-check.sh)"
# ---- prompt-check: kill switch
touch "$HP_PROJ/.claude/hooks.off"
expect_no_out "prompt-check off switch" 'done-signal' "$(run_prompt "implement the SchemaFactory")"
rm "$HP_PROJ/.claude/hooks.off"

# ---- vendor-guard (V = the vendor directory name, kept out of literal commands)
V=vendor
vg() { CLAUDE_PROJECT_DIR="$HP_PROJ" bash $H/vendor-guard.sh; }
vbash() { jq -n --arg c "$1" '{tool_name:"Bash",tool_input:{command:$c}}' | vg; }
expect_out    "vendor-guard denies Read" '"deny"' "$(jq -n --arg f "/x/$V/mcp/sdk/src/Server.php" '{tool_name:"Read",tool_input:{file_path:$f}}' | vg)"
expect_out    "vendor-guard denies Grep path" '"deny"' "$(jq -n --arg p "$V/mcp" '{tool_name:"Grep",tool_input:{pattern:"Builder",path:$p}}' | vg)"
expect_out    "vendor-guard denies cat" '"deny"' "$(vbash "cat $V/mcp/sdk/README.md")"
expect_out    "vendor-guard denies grep -r" '"deny"' "$(vbash "grep -rn input_required $V/")"
expect_no_out "vendor-guard allows subagent" 'deny' "$(jq -n --arg f "$V/mcp/sdk/src/Server.php" '{agent_id:"a1",agent_type:"verify-researcher",tool_name:"Read",tool_input:{file_path:$f}}' | vg)"
expect_no_out "vendor-guard allows bin tools" 'deny' "$(vbash "$V/bin/phpunit --no-progress | tail -5")"
expect_no_out "vendor-guard allows src read" 'deny' "$(jq -n '{tool_name:"Read",tool_input:{file_path:"src/RefundDesk/Entity/RefundCase.php"}}' | vg)"
expect_no_out "vendor-guard allows composer" 'deny' "$(vbash "composer require mcp/sdk")"
expect_no_out "vendor-guard ignores heredoc body" 'deny' "$(vbash "$(printf 'cat > t.sh <<EOF\ncat %s/x/README.md\nEOF' "$V")")"
expect_no_out "vendor-guard ignores single quotes" 'deny' "$(vbash "jq -n '{c:\"cat $V/x\"}'")"
expect_out    "vendor-guard warn mode" 'additionalContext' "$(jq -n --arg f "$V/x.php" '{tool_name:"Read",tool_input:{file_path:$f}}' | LEASH_VENDOR_GUARD=warn CLAUDE_PROJECT_DIR="$HP_PROJ" bash $H/vendor-guard.sh)"

# ---- session-start (against a git fixture repo)
SS="$TMP/ss"; mkdir -p "$SS/docs" "$SS/.claude"; cp docs/PROGRESS.md "$SS/docs/"
( cd "$SS" && git init -q -b main && git add -A && git -c user.email=t@t -c user.name=t commit -qm "fixture commit" )
out=$(echo '{}' | CLAUDE_PROJECT_DIR="$SS" bash $H/session-start.sh)
expect_out "session-start: valid JSON with context" '^SessionStart$' "$(printf '%s' "$out" | jq -r '.hookSpecificOutput.hookEventName')"
ctxt=$(printf '%s' "$out" | jq -r '.hookSpecificOutput.additionalContext')
expect_out    "session-start: has Current"      'Phase: ' "$ctxt"
expect_out    "session-start: has next prompt"  'Next prompt: /phase' "$ctxt"
expect_out    "session-start: has last commit"  'fixture commit' "$ctxt"
expect_no_out "session-start: no HTML comments" '<!--' "$ctxt"
[ "$(printf '%s' "$ctxt" | wc -c)" -le 2000 ] && ok || bad "session-start: context over 2000 chars"

# ---- phase-boundary
pb() { jq -n --arg c "$1" '{tool_name:"Bash",tool_input:{command:$c}}' | CLAUDE_PROJECT_DIR="$SS" bash $H/phase-boundary.sh; }
expect_no_out "phase-boundary: normal commit quiet" 'Phase boundary' "$(pb 'git commit -m x')"
( cd "$SS" && git -c user.email=t@t -c user.name=t commit -q --allow-empty -m "Phase end: M1 done" )
expect_out    "phase-boundary: phase-end commit" 'Run /clear, then: /phase' "$(pb 'git commit -m "Phase end: M1 done"')"
expect_no_out "phase-boundary: non-commit command quiet" 'Phase boundary' "$(pb 'git status')"

# ---- timing budget: each hook < 200 ms
for h in prompt-check vendor-guard session-start phase-boundary; do
  s=$(python3 -I -c 'import time;print(int(time.time()*1000))')
  echo '{"tool_name":"Read","tool_input":{"file_path":"src/a.php"},"prompt":"hi"}' | CLAUDE_PROJECT_DIR="$SS" bash $H/$h.sh >/dev/null
  e=$(python3 -I -c 'import time;print(int(time.time()*1000))')
  [ $((e - s)) -lt 200 ] && ok || bad "$h took $((e - s)) ms (budget 200)"
done
