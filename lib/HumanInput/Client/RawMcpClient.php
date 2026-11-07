<?php

declare(strict_types=1);

namespace Leash\HumanInput\Client;

use Mcp\Client\Stateless\HeaderFactory;
use Mcp\Client\Stateless\RequestEnvelope;
use Mcp\Client\Stateless\ToolCatalog;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Implementation;
use Mcp\Schema\Result\CallToolResult;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Workaround (BUGS.md, decisions/017): `Mcp\Client::callTool()` resolves `input_required` itself and
 * cannot send a later retry. This client sends one stateless 2026-07-28 `tools/call` over PSR-18 and
 * returns the raw `input_required` or the final result; a resume is the same call plus answers.
 * Envelope and headers come from the SDK helpers (decisions/021).
 */
final class RawMcpClient
{
    private const ProtocolVersion VERSION = ProtocolVersion::V2026_07_28;

    private int $id = 0;

    public function __construct(
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
    ) {}

    /**
     * @param array<string, array<string, mixed>>|null $inputResponses key => `ElicitResult` wire shape (decisions/020)
     *
     * @throws McpCallFailed
     * @throws ClientExceptionInterface on transport failure (worth a retry)
     */
    public function call(ToolCall $call, #[\SensitiveParameter] string $token, ?array $inputResponses = null, ?string $requestState = null): InputRequired|CallToolResult
    {
        $params = ['name' => $call->tool, 'arguments' => (object) $call->arguments];
        if (null !== $inputResponses) {
            $params['inputResponses'] = (object) $inputResponses;
        }
        if (null !== $requestState) {
            $params['requestState'] = $requestState;
        }
        $envelope = new RequestEnvelope(self::VERSION, new ClientCapabilities(elicitationForm: true), new Implementation('leash', 'dev'));
        $payload = $envelope->stamp(['jsonrpc' => '2.0', 'id' => ++$this->id, 'method' => 'tools/call', 'params' => $params]);

        $request = $this->requests->createRequest('POST', $call->endpoint)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $token)
            ->withBody($this->streams->createStream(json_encode($payload, \JSON_THROW_ON_ERROR)));
        foreach (new HeaderFactory(new ToolCatalog())->forMessage($payload, self::VERSION) as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $response = $this->http->sendRequest($request);
        $body = (string) $response->getBody();
        $message = str_contains(strtolower($response->getHeaderLine('Content-Type')), 'text/event-stream') ? self::lastSseMessage($body) : self::decode($body);

        if (\is_array($message['error'] ?? null)) {
            $code = $message['error']['code'] ?? 0;
            $text = $message['error']['message'] ?? '';
            throw McpCallFailed::jsonRpc(\is_int($code) ? $code : 0, \is_string($text) ? $text : '');
        }
        if ($response->getStatusCode() >= 300 || !\is_array($message['result'] ?? null)) {
            throw McpCallFailed::http($response->getStatusCode(), $body);
        }
        $result = $message['result'];

        try {
            return InputRequired::fromResult($result) ?? CallToolResult::fromArray($result);
        } catch (\InvalidArgumentException $e) {
            throw McpCallFailed::malformed($e->getMessage(), $e);
        }
    }

    /** @return array<mixed>|null */
    private static function decode(string $json): ?array
    {
        try {
            $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($data) ? $data : null;
    }

    /**
     * Fallback for an SSE answer: the last `data:` frame that is a JSON-RPC response wins.
     *
     * @return array<mixed>|null
     */
    private static function lastSseMessage(string $body): ?array
    {
        $last = null;
        foreach (explode("\n", $body) as $line) {
            if (str_starts_with($line, 'data:')) {
                $data = self::decode(trim(substr($line, 5)));
                if (null !== $data && (\array_key_exists('result', $data) || \array_key_exists('error', $data))) {
                    $last = $data;
                }
            }
        }

        return $last;
    }
}
