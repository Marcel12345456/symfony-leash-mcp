# Upstream issues (filed 2026-11-22)

Issue texts for the reproduced bugs in [`BUGS.md`](../BUGS.md), for a human to file (PLAN M8). The "Candidates" in
`BUGS.md` are not reproduced and are not here.

Re-checked on 2026-11-22 against the latest code: symfony/ai v1.0.0-BETA1 and main (f56c759), mcp/sdk v0.10.0 and
main (7155eae). Issues 1–4 were re-run in Docker against BETA1 and dev-main; 5–7 were checked by reading the code.
Parts already fixed upstream were dropped, and issue 4 is not filed (see there).

**Status 2026-11-22 (evening):** all six open, no maintainer comments, no linked PRs. symfony/ai #3034–#3036 labelled
`Platform`; #3037, php-sdk #767 and #768 not yet triaged.

Trackers: `symfony/ai-*` → https://github.com/symfony/ai/issues, `mcp/sdk` → https://github.com/modelcontextprotocol/php-sdk/issues.

---

## 1. [Platform] JsonSchema Factory: nullable properties without default are required; NotBlank emits OpenAPI "nullable"

**Filed:** https://github.com/symfony/ai/issues/3034

**Package:** symfony/ai-platform 1.0.0-BETA1 / main (f56c759)

```php
enum Verdict: string { case Keep = 'keep'; case Drop = 'drop'; }

final class ReviewInput
{
    public function __construct(
        #[Assert\Positive] public int $score,
        #[Assert\NotBlank] public ?string $note,
        public ?Verdict $previous = null,
    ) {}
}

$schema = (new Symfony\AI\Platform\Contract\JsonSchema\Factory())->buildProperties(ReviewInput::class);
```

**Actual (main):** `{"score":{"type":"integer","exclusiveMinimum":0},"note":{"type":["string","null"],"nullable":false,"minLength":1},"previous":{"type":["string","null"],"enum":["keep","drop",null]},…,"required":["score","note"]}`
- `note` is required although it is nullable.
- `NotBlank` adds OpenAPI's `nullable` keyword, which is not JSON Schema, and here it is `false` while `type` allows `null`.

(The enum/null and `exclusiveMinimum` problems are fixed on main by #2836 and #2888, but are still present in
1.0.0-BETA1.)

**Expected:** no OpenAPI-only keywords. Either nullable properties are optional, or this is documented.

**Context:** MCP servers publish these schemas as tool `inputSchema`; strict clients reject or mis-fill them. We
normalise the output ourselves.

---

## 2. [Platform] JsonSchema Factory: mixed property produces {"type":["null"]} and PHP warnings

**Filed:** https://github.com/symfony/ai/issues/3035

**Package:** symfony/ai-platform 1.0.0-BETA1 / main (f56c759)

```php
final class X
{
    public function __construct(public mixed $anything = null) {}
}
(new Symfony\AI\Platform\Contract\JsonSchema\Factory())->buildProperties(X::class);
```

**Actual:** `anything` → `{"type": ["null"]}`, plus two warnings `Undefined array key "type"` in
`src/Contract/JsonSchema/Describer/TypeInfoDescriber.php` line 77. `mixed` counts as nullable but has no type schema.

**Expected:** `mixed` → no type constraint (or an exception), and no warnings.

---

## 3. [Platform] `RecordingProvider` default signature ignores the message content

**Filed:** https://github.com/symfony/ai/issues/3036

**Package:** symfony/ai-platform 1.0.0-BETA1 / main (f56c759)

```php
$platform = new Platform([new RecordingProvider($provider, new Cassette($path), record: true)]);
$platform->invoke('m', new MessageBag(Message::ofUser('a')))->getResult();
$platform->invoke('m', new MessageBag(Message::ofUser('b')))->getResult();
// read both "signature" values from the cassette JSON
```

**Actual:** both signatures are equal. The default signature hashes `json_encode([$model, $input, $options])`, and a
`MessageBag` (private properties, no `JsonSerializable`) encodes as `{}`. Replay then serves answers FIFO in global
call order, so a changed prompt silently gets the old answer. `Cassette` also has no way to read all interactions.

**Expected:** the signature reflects the messages, e.g. normalise the bag with the `MessageBagNormalizer` added in
#2799, or the documentation requires a custom signature closure.

---

## 4. Not filed: `ai.platform.ollama` and the configured HTTP client

Not reproducible upstream (re-checked 2026-11-22): in a minimal kernel on 0.15.0 and main, a configured client
timeout of 3 s was honoured (`AiBundle` passes `new Reference($platform['http_client'])`). The 60 s timeout seen in
Leash is most likely our own configuration, e.g. a scoped client only applies to URLs matching its `base_uri`.
Earlier related issues #899 and #917 are closed.

---

## 5. [Ollama Platform] Token usage has no total

**Filed:** https://github.com/symfony/ai/issues/3037

**Package:** symfony/ai-ollama-platform 1.0.0-BETA1 / main (f56c759)

**Repro:** an agent call on `ai.platform.ollama` (`qwen2.5:7b`), then
`$result->getMetadata()->get('token_usage')->getTotalTokens()`.

**Actual:** prompt 425, completion 59, total `null`. `Bridge/Ollama/TokenUsageExtractor.php` builds `TokenUsage`
without `totalTokens`.

**Expected:** total = prompt + completion (Ollama returns `prompt_eval_count` and `eval_count`).

---

## 6. [Client] `callTool()` cannot defer `input_required` or send a later retry (MRTR)

**Filed:** https://github.com/modelcontextprotocol/php-sdk/issues/767

**Package:** mcp/sdk 0.9.0; still present in v0.10.0 and main (7155eae)

**Repro:** `$client->callTool('x', [...])` against a tool that returns `InputRequiredResult`, from a client that wants
to park the request until a human answers hours later.

**Actual:** `Client\Protocol::request()` always resolves rounds through the elicitation handlers
(`src/Client/Protocol.php:560-627`, `MAX_ROUND_TRIPS` at line 69). `requestState` is never exposed, and `callTool()`
takes no `inputResponses` / `requestState`. Throwing from the callback gives a message-only `RuntimeException`
(`InputRequestResolver.php:145,163`).

**Expected:** an option to return the raw `InputRequiredResult`, and a way to send the retry with `inputResponses`
and `requestState`. Deferral is what multi-round-trip requests are designed for. I understand automatic resolution
is deliberate (#456); this asks for an opt-out next to it. The Tasks extension (#428) covers task-augmented
requests, not plain MRTR.

---

## 7. [Client] `Builder::addRequestHandler()` rejects the SDK's own handlers under PHPStan

**Filed:** https://github.com/modelcontextprotocol/php-sdk/issues/768

**Package:** mcp/sdk 0.9.0; still present in v0.10.0 and main (`src/Client/Builder.php:177-179`,
`src/Client/Handler/Request/RequestHandlerInterface.php:21`)

```php
Client::builder()->addRequestHandler(new ElicitationRequestHandler($callback));
```

**Actual (PHPStan level max):** `Parameter #1 expects RequestHandlerInterface<mixed>, ElicitationRequestHandler given`;
the template parameter is invariant.

**Expected:** accepted: declare the template covariant (`@template-covariant`) or type the parameter
`RequestHandlerInterface<*>`.
