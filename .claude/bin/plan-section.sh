#!/bin/sh
# Print one section of docs/PLAN.md, so a session loads only what the task needs.
#   plan-section.sh M1      track phase (M1..M6, W1..W3)
#   plan-section.sh G0      gate (G0..G2)
#   plan-section.sh 0       Phase 0
#   plan-section.sh 3.2     subsection      plan-section.sh 7    section
# Exit 1 if the section does not exist.
key="${1:?usage: plan-section.sh <M1|W2|G0|0|3.2|7>}"
plan="${PLAN_FILE:-$(dirname "$0")/../../docs/PLAN.md}"

awk -v key="$key" '
  function heading_level(l) { if (l ~ /^### /) return 3; if (l ~ /^## /) return 2; return 0 }
  BEGIN {
    if (key ~ /^(P?0)$/)            { mode = "h"; start = "^### Phase 0 "; lvl = 3 }
    else if (key ~ /^[0-9]+$/)      { mode = "h"; start = "^## " key "\\. "; lvl = 2 }
    else if (key ~ /^[0-9]+\.[0-9]+$/) { k = key; gsub(/\./, "\\.", k); mode = "h"; start = "^### " k " "; lvl = 3 }
    else if (key ~ /^G[0-9]$/)      { mode = "b"; start = "^\\*\\*Gate " key " " }
    else if (key ~ /^[MW][0-9]$/)   { mode = "b"; start = "^\\*\\*" key " " }
    else { print "unknown section key: " key > "/dev/stderr"; bad = 1; exit }
  }
  /^```/ { fence = !fence }
  {
    if (!on) { if (!fence && $0 ~ start) { on = 1; found = 1; print; next } else next }
    if (!fence) {
      h = heading_level($0)
      if (mode == "h" && h > 0 && h <= lvl) exit
      if (mode == "b" && (h > 0 || $0 ~ /^\*\*([MW][0-9] |Gate )/)) exit
    }
    print
  }
  END { if (bad) exit 2; if (!found) exit 1 }
' "$plan"
