# 007 — HF/Ollama bridges, replay tooling, local store, Workflow Mermaid

- Date: 2026-11-06
- Status: accepted (Mermaid part re-checked once symfony/workflow is installed)
- PLAN reference: §3.4 HF tool calling VERIFY; replay mode VERIFY; RAG store VERIFY; dashboard Mermaid dumper VERIFY

## Question

Can the HF and Ollama bridges do tool calling and embeddings, can agent runs be replayed offline, which store needs no external service, and can Symfony Workflow dump Mermaid with the current marking highlighted?

## Answer

symfony/ai-* 0.15.0; symfony/workflow 8.1 read on GitHub (not installed).

- **HF chat:** `https://router.huggingface.co` (`vendor/symfony/ai-hugging-face-platform/Factory.php:44`), OpenAI-compatible `/v1/chat/completions` via the Generic client (`ai-generic-platform/Completions/ModelClient.php:44`). Provider via `Factory` `$provider` or per-call `provider` option; model sent as `<model>:<provider>` (`:41-48`). Tool calling supported (CHANGELOG 0.15; `CompletionsConversionTrait.php:274` → `ToolCallResult`), gated per model by `Capability::TOOL_CALLING` in `ModelCatalog.php` (unlisted models resolved via Hub API, `ModelApiCatalog.php:87`). Token usage via `TokenUsageExtractor` (`ResultConverter.php:105-107`).
- **HF embeddings:** `{base}/hf-inference/models/<model>/pipeline/feature-extraction` (`Pipeline/ModelClient.php:49-53`); no token usage (`Embeddings/ResultConverter.php:46-49`).
- **Ollama:** `/api/chat`, `/api/embed` (`OllamaClient.php:86,102`); tool calls parsed (`OllamaResultConverter.php:80,142`); token usage; capabilities from the server (`ModelCatalog.php:68-75`).
- **Testing:** `ai-platform/src/Test/MockPlatformFactory::createPlatform($responses)` (Closure, string or model→`ResultInterface`; no built-in sequence → Closure with counter). `Test/Recording/RecordingProvider` + `Cassette`: record a real run to JSON, replay offline, FIFO per signature (`Cassette.php:19-24,70-86`); default signature = hash of model+input+options (`RecordingProvider.php:122-132`), custom `$signature` closure possible (`:59-62`); replays when the file exists (`:73`).
- **Stores:** `Store\InMemory\Store` (no persistence); `Store\Bridge\Sqlite\Store` is pure PHP + `pdo_sqlite`, vectors as TEXT, FTS5, distance in PHP, vector/text/hybrid queries (`ai-sqlite-store/Store.php:44-58`). Avoid `VecStore` (needs sqlite-vec). File DSN via `StoreFactory::create($dsn, $table)`.
- **Mermaid:** `Symfony\Component\Workflow\Dumper\MermaidDumper` exists; marked places get `stroke-width:4px`. `workflow:dump <name> <place…> --dump-format=mermaid`.

## Decision

- Platform: HF router chat completions, tool-capable model, explicit `provider`; check `TOOL_CALLING` in the catalog. Ollama as local fallback.
- Replay mode (`LEASH_MODEL=replay`): `RecordingProvider` + committed `Cassette` JSON with a signature closure that normalizes ids/timestamps. Unit tests: `MockPlatformFactory` with a counter Closure.
- RAG: pure-PHP `Sqlite\Store` in a committed `.sqlite` file; embeddings via HF `hf-inference` or Ollama.
- Dashboard: `MermaidDumper` in PHP with the current marking.

## Consequences

- SpendGuard cannot count embedding tokens (null usage) → estimate or treat as free.
- Generic cassette signature helper needed (no domain code).
- Re-check `MermaidDumper` and the dump command against the installed `symfony/workflow` in M1.
