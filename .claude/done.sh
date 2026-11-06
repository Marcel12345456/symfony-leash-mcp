#!/bin/sh
# Definition of Done for Leash. Exit 0 = done.
# Cheapest checks first. `make qa` joins automatically once the Makefile has a qa target.
set -e
cd "$(dirname "$0")/.."

sh .claude/checks/docs.sh
sh .claude/tests/run.sh

if [ -f Makefile ] && grep -q '^qa:' Makefile; then
  make -s qa
fi
