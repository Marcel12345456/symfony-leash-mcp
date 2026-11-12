# Leash

Agents you can deploy: the workflow state and the agent's role decide which MCP tools exist;
when a human is needed, the request leaves the chat and resumes when the human answers.

Built for the Symfony AI & PHP MCP SDK hackathon.

## Requirements

Docker. PHP 8.4, Composer and SQLite run in the toolchain container (`compose.yaml`);
nothing else is installed on the host.

## Commands

```
make install      # composer install, warm the cache, migrate, seed the Doc Review demo documents; generate APP_SECRET + Werewolf secret into .env.local
make werewolf     # play one Werewolf game: replays data/werewolf/seed-8.json (SEED=…, LEASH_MODEL=ollama for live, HUMAN_SEAT=n: you play seat n via the inbox)
make work         # run the Doc Review agents once over all open documents (bin/console leash:work; LEASH_MODEL=ollama)
make test         # phpunit
make qa           # php-cs-fixer (changed files) + phpstan + phpunit, quiet
make qa-verbose   # the same with full output, whole tree
make cs-fix       # apply php-cs-fixer
make inspector TOKEN=...  # MCP Inspector against /mcp/doc_review/d1 (INSPECTOR_DOC=…); needs host Node ^22.23.4 or ^24.22.1
```

Inside the container, or with a local PHP 8.4, pass `NATIVE=1` to skip Docker.

## Stack

Symfony 8.1, PHP 8.4, Doctrine ORM on SQLite (WAL, `busy_timeout` 5000 ms).
Pinned pre-release libraries: `symfony/ai-*` 0.15.0, `symfony/mcp-bundle` 0.15.0, `mcp/sdk` 0.9.0.

## License

MIT
