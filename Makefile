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

.PHONY: install qa qa-verbose test cs-fix inspector werewolf work

install: ## Generates APP_SECRET, the Werewolf token secret and the inbox approver password into .env.local on first run; never overwrites them
	@grep -qs '^APP_SECRET=.' .env.local || echo "APP_SECRET=$$(openssl rand -hex 16)" >> .env.local
	@grep -qs '^LEASH_WEREWOLF_SECRET=.' .env.local || echo "LEASH_WEREWOLF_SECRET=$$(openssl rand -hex 24)" >> .env.local
	@grep -qs '^APPROVER_PASSWORD=.' .env.local || echo "APPROVER_PASSWORD=$$(openssl rand -hex 12)" >> .env.local
	$(RUN) composer install --no-interaction --no-progress -q
	$(RUN) bin/console cache:warmup -q
	$(RUN) bin/console doctrine:migrations:migrate -n -q
	$(RUN) bin/console leash:demo:seed -q

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

# Replays the committed cassette data/werewolf/seed-$(SEED).json (no model, no network); LEASH_MODEL=ollama plays live.
SEED ?= 8
werewolf: ## make werewolf [SEED=8] [LEASH_MODEL=ollama] [HUMAN_SEAT=n]; transcript in var/werewolf/seed-<seed>.md
	$(RUN) bin/console leash:werewolf:play --seed=$(SEED) $(if $(LEASH_MODEL),--model=$(LEASH_MODEL)) $(if $(HUMAN_SEAT),--human-seat=$(HUMAN_SEAT))

# The Doc Review agents work every open document once (Ollama; replay is not available for Doc Review yet).
work: ## make work [LEASH_MODEL=ollama]
	$(RUN) bin/console leash:work $(if $(LEASH_MODEL),--model=$(LEASH_MODEL))

cs-fix:
	$(RUN) vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

# MCP Inspector (host Node) against a Doc Review document; php -S runs in the toolchain container meanwhile.
INSPECTOR_DOC ?= d1
inspector: ## make inspector TOKEN=<agent bearer token> [INSPECTOR_DOC=d1]
	@test -n "$(TOKEN)" || { echo "usage: make inspector TOKEN=<Doc Review agent token (DocReviewCallers::token)> [INSPECTOR_DOC=d1]" >&2; exit 2; }
	@node -e 'const [a,b,c]=process.versions.node.split(".").map(Number), v=b*1e4+c; process.exit((a===22&&v>=230004)||(a===24&&v>=220001)||(a===26&&v>=120001)||a>26?0:1)' \
	  || { echo "MCP Inspector needs Node ^22.23.4, ^24.22.1 or >=26.12.1 (found $$(node --version)); e.g. nvm install 24" >&2; exit 2; }
	@docker compose --progress quiet run -d --rm --service-ports --no-deps --name leash-inspector-php php php -S 0.0.0.0:8000 -t public >/dev/null
	@sh -c 'trap "docker stop leash-inspector-php >/dev/null" EXIT INT TERM; \
	  npx -y @modelcontextprotocol/inspector@2.14.0 --server-url "http://127.0.0.1:$${LEASH_PORT:-8000}/mcp/doc_review/$(INSPECTOR_DOC)" --transport http --header "Authorization: Bearer $(TOKEN)"'
