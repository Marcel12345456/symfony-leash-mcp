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
make demo         # dashboard on http://127.0.0.1:8000/ + worker (+ Telegram poller), then a paced replay of the story, no model (PACE=2, LEASH_MODEL=ollama for live)
make werewolf     # play one Werewolf game: replays data/werewolf/seed-8.json (SEED=…, LEASH_MODEL=ollama for live, HUMAN_SEAT=n: you play seat n via the inbox)
make work         # run the Doc Review agents once over all open documents: replays data/docreview/demo.json after `bin/console leash:demo:seed --reset` (LEASH_MODEL=ollama for live)
make test         # phpunit
make qa           # php-cs-fixer (changed files) + phpstan + phpunit, quiet
make qa-verbose   # the same with full output, whole tree
make cs-fix       # apply php-cs-fixer
make inspector TOKEN=...  # MCP Inspector against /mcp/doc_review/d1 (INSPECTOR_DOC=…); needs host Node ^22.23.4 or ^24.22.1
```

Inside the container, or with a local PHP 8.4, pass `NATIVE=1` to skip Docker.

## Telegram (optional)

Pending human input is also sent to Telegram when `.env.local` sets
`TELEGRAM_DSN=telegram://BOTID:TOKEN@default?channel=CHAT_ID` (your private chat with the bot).
A pick list or yes/no comes with one button per option; anything else links to the inbox.
`bin/console leash:telegram:poll` receives the button presses (only from that chat) and answers the input;
the messenger worker resumes the call. Without the DSN nothing is sent. `make demo` starts the poller when the DSN is set.

## Dashboard

`/` shows the Werewolf spectator view (public log, denied calls, and per seat the tools and resources the MCP server
lists for that seat right now; "Reveal roles" adds roles and private knowledge) and the Doc Review tab (workflow
diagram with the current place, timeline: red = denied, amber = waits for a human, green = transition).
It is public on purpose: it is the local demo screen.

## Stack

Symfony 8.1, PHP 8.4, Doctrine ORM on SQLite (WAL, `busy_timeout` 5000 ms).
Pinned pre-release libraries: `symfony/ai-*` 0.15.0, `symfony/mcp-bundle` 0.15.0, `mcp/sdk` 0.9.0.

## License

MIT
