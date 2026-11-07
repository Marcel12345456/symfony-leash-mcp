<?php

declare(strict_types=1);

namespace Leash\HumanInput\Client;

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;

/**
 * Calls a tool with the caller's token and hands every `input_required` to the input request handler.
 * Answered → retry at once; deferred → the caller gets a "paused, do not retry" result and stops.
 */
final readonly class HumanInputToolCaller
{
    public const int MAX_ROUNDS = 10;

    public function __construct(
        private RawMcpClient $client,
        private InputRequestHandlerInterface $handler,
        private CallerTokens $tokens,
    ) {}

    /** @throws McpCallFailed */
    public function call(ToolCall $call): CallToolResult
    {
        $token = $this->tokens->tokenFor($call->callerId);
        $result = $this->client->call($call, $token);

        for ($rounds = 0;; ++$rounds) {
            if (!$result instanceof InputRequired) {
                return $result;
            }
            if (self::MAX_ROUNDS === $rounds) {
                throw new McpCallFailed(\sprintf('"%s" still asks for input after %d answers.', $call->tool, self::MAX_ROUNDS));
            }
            $outcome = $this->handler->handle($result, $call);
            if (null !== $outcome->pendingId) {
                return self::paused($outcome->pendingId);
            }
            $result = $this->client->call($call, $token, $outcome->responses ?? [], $result->requestState);
        }
    }

    public static function paused(string $pendingId): CallToolResult
    {
        return new CallToolResult(
            [new TextContent(\sprintf('A human has been asked to approve this (request #%s). This item is paused. Do not retry; stop working on it.', $pendingId))],
            false,
            ['paused' => true, 'pendingInput' => $pendingId],
        );
    }
}
