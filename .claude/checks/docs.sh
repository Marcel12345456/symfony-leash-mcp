#!/bin/sh
# Repository hygiene. Run from the repo root (or pass it as $1).
#   - single source of truth: no files/ staging folder, no Markdown file twice
#   - CLAUDE.md stays small (it is loaded on every turn)
#   - make qa stays quiet (its output lands in the context)
root="${1:-.}"
cd "$root" || exit 2
fail=0
err() { echo "docs-check: $*" >&2; fail=1; }

[ -d files ] && err "files/ exists. Edit CLAUDE.md and docs/ in place; do not keep copies."

dups=$(find . -name '*.md' -not -path './vendor/*' -not -path './node_modules/*' -not -path './var/*' \
         -not -path './.git/*' -not -path './.claude/*' -not -path './lib/*/README.md' \
         -not -name README.md -exec basename {} \; | sort | uniq -d)
[ -n "$dups" ] && err "Markdown file present more than once: $(echo $dups)"

if [ -f CLAUDE.md ]; then
  size=$(wc -c < CLAUDE.md | tr -d ' ')
  [ "$size" -gt 7000 ] && err "CLAUDE.md is $size bytes (limit 7000). Move detail into docs/PLAN.md."
fi

if [ -f Makefile ] && grep -q '^qa:' Makefile; then
  grep -q -- '--no-progress' Makefile || err "Makefile qa target is not quiet (expected --no-progress)."
fi

exit $fail
