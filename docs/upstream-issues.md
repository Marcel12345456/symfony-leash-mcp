# Upstream issues, ready to file

Issue texts for the reproduced bugs in [`BUGS.md`](../BUGS.md), for a human to file (PLAN M8). Before filing: check
the tracker for an existing issue and try the latest release; check the repository URL. The "Candidates" in
`BUGS.md` are not reproduced and are not here.

Trackers: `symfony/ai-*` → https://github.com/symfony/ai/issues, `mcp/sdk` → https://github.com/modelcontextprotocol/php-sdk/issues.

---

## 1. [Platform] JsonSchema Factory: nullable enum excludes null, nullable properties are required, draft-4 `exclusiveMinimum`

**Package:** symfony/ai-platform 0.15.0

```php
enum Verdict: string { case Keep = 'keep'; case Drop = 'drop'; }

final class ReviewInput
{
    public function __construct(
        #[Assert\Positive] public int $score,
        public ?string $note,
        public ?Verdict $previous = null,
    ) {}
}

$schema = (new Symfony\AI\Platform\Contract\JsonSchema\Factory())->buildProperties(ReviewInput::class);
```

**Actual**
- `previous` → `{"type": ["string", "null"], "enum": ["keep", "drop"]}`: `null` fails the enum, so the declared type can never be sent.
- `note` is listed in `required` although it is nullable (debatable, but then clients must send `null`).
- `score` gets `"exclusiveMinimum": true` (draft-4 boolean form); JSON Schema 2020-12 expects a number.
- `NotBlank` adds OpenAPI's `nullable` keyword.

**Expected:** `enum` includes `null` for nullable enums; numeric `exclusiveMinimum`; no OpenAPI-only keywords.

**Context:** MCP servers publish these schemas as tool `inputSchema`; strict clients reject or mis-fill them. We
normalise the output ourselves.

---

## 2. [Platform] JsonSchema Factory: `mixed` and union properties produce wrong schemas and PHP warnings

**Package:** symfony/ai-platform 0.15.0

```php
final class X
{
    public function __construct(public mixed $anything = null, public int|string $either = 1) {}
}
(new Symfony\AI\Platform\Contract\JsonSchema\Factory())->buildProperties(X::class);
```

**Actual:** `anything` → `{"type": ["null"]}` (only `null` is valid); `either` → `[]` (no type); two warnings
`Undefined array key "type"` in `Contract/JsonSchema/Describer/TypeInfoDescriber.php` line 77.
(A plain `object` throws `InvalidArgumentException`, which is fine.)

**Expected:** `mixed` → no type constraint, or an exception; `int|string` → `{"type": ["integer", "string"]}`, or an
exception. No warnings.

---

## 3. [Platform] `RecordingProvider` default signature ignores the message content

**Package:** symfony/ai-platform 0.15.0

```php
$platform = new Platform([new RecordingProvider($provider, new Cassette($path), record: true)]);
$platform->invoke('m', new MessageBag(Message::ofUser('a')))->getResult();
$platform->invoke('m', new MessageBag(Message::ofUser('b')))->getResult();
// read both "signature" values from the cassette JSON
```

**Actual:** both signatures are equal. The default signature hashes `json_encode([$model, $input, $options])`, and a
`MessageBag` (private properties, no `JsonSerializable`) encodes as `{}`. Replay then serves answers FIFO in global
call order, so a changed prompt silently gets the old answer. `Cassette` also has no way to read all interactions.

**Expected:** the signature reflects the messages (normalise the bag), or the documentation requires a custom
signature closure.

---

## 4. [AI Bundle] `ai.platform.ollama` ignores the configured HTTP client

**Package:** symfony/ai-bundle 0.15.0 with symfony/ai-ollama-platform 0.15.0

**Repro:** configure `ai.platform.ollama.http_client` with a scoped client that has `timeout: 3` (or set
`framework.http_client.default_options.timeout: 3`); call a slow model (e.g. `qwen3` with a long prompt).

**Actual:** fails with `Idle timeout reached` after 60.2 s both times. With `php -d default_socket_timeout=3` it fails
after 3.2 s, so the platform's client takes its timeout from `default_socket_timeout`.

**Expected:** the configured `http_client`, and its timeout, is used.

---

## 5. [Ollama Platform] Token usage has no total

**Package:** symfony/ai-ollama-platform 0.15.0

**Repro:** an agent call on `ai.platform.ollama` (`qwen2.5:7b`), then
`$result->getMetadata()->get('token_usage')->getTotalTokens()`.

**Actual:** prompt 425, completion 59, total `null`.

**Expected:** total = prompt + completion (Ollama returns `prompt_eval_count` and `eval_count`).

---

## 6. [Client] `callTool()` cannot defer `input_required` or send a later retry (MRTR)

**Package:** mcp/sdk 0.9.0

**Repro:** `$client->callTool('x', [...])` against a tool that returns `InputRequiredResult`, from a client that wants
to park the request until a human answers hours later.

**Actual:** `Client\Protocol::request()` always resolves rounds through the elicitation handlers (at most 10,
`Client/Protocol.php` around lines 555–620). `requestState` is never exposed, and `callTool()` takes no
`inputResponses` / `requestState`. Throwing from the callback gives a message-only `RuntimeException`.

**Expected:** an option to return the raw `InputRequiredResult`, and a way to send the retry with `inputResponses`
and `requestState`. Deferral is what multi-round-trip requests are designed for.

---

## 7. [Client] `Builder::addRequestHandler()` rejects the SDK's own handlers under PHPStan

**Package:** mcp/sdk 0.9.0

```php
Client::builder()->addRequestHandler(new ElicitationRequestHandler($callback));
```

**Actual (PHPStan level max):** `Parameter #1 expects RequestHandlerInterface<mixed>, ElicitationRequestHandler given`;
the template parameter is invariant.

**Expected:** accepted: declare the template covariant (`@template-covariant`) or type the parameter
`RequestHandlerInterface<*>`.
