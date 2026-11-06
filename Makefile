# Leash. Every PHP command runs in the toolchain container (compose.yaml), so the host
# needs only Docker. Inside the container or with NATIVE=1, commands run directly.
ifeq ($(wildcard /.dockerenv)$(NATIVE),)
export UID := $(shell id -u)
export GID := $(shell id -g)
RUN := docker compose --progress quiet run --rm --no-deps php
else
RUN :=
endif

# PHP files changed against main (committed, staged, unstaged, untracked); cs-fixer checks only these in `qa`.
BASE := $(shell git merge-base HEAD origin/main 2>/dev/null || git merge-base HEAD main 2>/dev/null)
CHANGED_PHP := $(sort $(filter %.php,$(shell git diff --name-only --diff-filter=ACMR $(BASE) 2>/dev/null; git ls-files --others --exclude-standard 2>/dev/null)))

.PHONY: install qa qa-verbose test cs-fix

install: ## Generates APP_SECRET into .env.local on first run; never overwrites it
	@grep -qs '^APP_SECRET=.' .env.local || echo "APP_SECRET=$$(openssl rand -hex 16)" >> .env.local
	$(RUN) composer install --no-interaction --no-progress -q
	$(RUN) bin/console cache:warmup -q

qa: ## Quiet: prints a tool's output only when it fails, plus one summary line
	@$(RUN) sh -c 'quiet() { out=$$("$$@" 2>&1) || { printf "%s\n" "$$out"; exit 1; }; }; \
	  quiet bin/console cache:warmup --env=dev \
	  && quiet vendor/bin/php-cs-fixer fix --dry-run --diff --show-progress=none --path-mode=intersection --config=.php-cs-fixer.dist.php -- $(or $(CHANGED_PHP),src/Kernel.php) \
	  && quiet vendor/bin/phpstan analyse --no-progress --error-format=raw --memory-limit=1G \
	  && quiet vendor/bin/phpunit --no-progress --colors=never \
	  && printf "%s\n" "$$out" | tail -1 | sed "s/^/qa green: phpunit /"'

qa-verbose: ## Full output, whole tree
	$(RUN) sh -c 'bin/console cache:warmup --env=dev \
	  && vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php \
	  && vendor/bin/phpstan analyse --memory-limit=1G \
	  && vendor/bin/phpunit'

test:
	$(RUN) vendor/bin/phpunit --no-progress

cs-fix:
	$(RUN) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php
