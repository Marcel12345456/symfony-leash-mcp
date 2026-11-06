#!/bin/sh
# Tests for the repo automation (.claude/checks, .claude/hooks, .claude/bin).
# Prints only failures and a one-line summary.
cd "$(dirname "$0")/../.." || exit 2
ROOT=$(pwd)
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
pass=0; failn=0

ok()   { pass=$((pass + 1)); }
bad()  { failn=$((failn + 1)); echo "FAIL: $*" >&2; }
expect_exit() { # expected_code description command...
  want=$1; desc=$2; shift 2
  "$@" >/dev/null 2>&1; got=$?
  if [ "$got" -eq "$want" ]; then ok; else bad "$desc (exit $got, want $want)"; fi
}
expect_out() { # description grep-pattern output
  if printf '%s' "$3" | grep -Eq -- "$2"; then ok; else bad "$1: no match for /$2/ in: $(printf '%s' "$3" | head -c 300)"; fi
}
expect_no_out() { # description grep-pattern output
  if printf '%s' "$3" | grep -Eq -- "$2"; then bad "$1: unexpected /$2/"; else ok; fi
}
fixture() { # copy of the repo docs into a fresh dir, prints its path
  d="$TMP/fx$(date +%s%N)$$$failn$pass"; mkdir -p "$d/docs"
  cp CLAUDE.md "$d/"; cp docs/*.md "$d/docs/"; echo "$d"
}

# ---- checks/docs.sh
expect_exit 0 "docs: clean repo passes" sh .claude/checks/docs.sh "$ROOT"
d=$(fixture); mkdir "$d/files"
expect_exit 1 "docs: files/ fails" sh .claude/checks/docs.sh "$d"
d=$(fixture); mkdir "$d/files2"; cp "$d/docs/PLAN.md" "$d/files2/"
expect_exit 1 "docs: duplicate PLAN.md fails" sh .claude/checks/docs.sh "$d"
d=$(fixture); head -c 8000 /dev/zero | tr '\0' x > "$d/CLAUDE.md"
expect_exit 1 "docs: oversized CLAUDE.md fails" sh .claude/checks/docs.sh "$d"
d=$(fixture); printf 'qa:\n\tvendor/bin/phpunit\n' > "$d/Makefile"
expect_exit 1 "docs: noisy make qa fails" sh .claude/checks/docs.sh "$d"
d=$(fixture); printf 'qa:\n\tvendor/bin/phpunit --no-progress\n' > "$d/Makefile"
expect_exit 0 "docs: quiet make qa passes" sh .claude/checks/docs.sh "$d"

# ---- further suites (hooks, bin) are sourced from tests/*.t.sh
for t in .claude/tests/*.t.sh; do
  [ -f "$t" ] && . "$t"
done

echo "automation tests: $pass passed, $failn failed"
[ "$failn" -eq 0 ]
