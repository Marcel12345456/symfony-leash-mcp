# Sourced by run.sh. Tests for .claude/bin/plan-section.sh
PS=.claude/bin/plan-section.sh
out=$(sh $PS M1)
expect_out    "plan-section M1: starts at M1"   '^\*\*M1 — '  "$(printf '%s' "$out" | head -1)"
expect_no_out "plan-section M1: stops before M2" '\*\*M2 — '  "$out"
out=$(sh $PS 3.2)
expect_out    "plan-section 3.2: has YAML"      'doc_review:' "$out"
expect_no_out "plan-section 3.2: stops before 3.3" '^### 3\.3' "$out"
out=$(sh $PS 8)
expect_out    "plan-section 8: keeps fenced ## lines" '^## N\.' "$out"
out=$(sh $PS 0)
expect_out    "plan-section 0: includes gate G0" 'Gate G0' "$out"
expect_no_out "plan-section 0: stops before main track" '^### Main track' "$out"
expect_exit 1 "plan-section: missing gate fails" sh $PS G9
expect_exit 2 "plan-section: bad key fails" sh $PS nonsense
