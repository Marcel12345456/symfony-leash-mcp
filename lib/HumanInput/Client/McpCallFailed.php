<?php

declare(strict_types=1);

namespace Leash\HumanInput\Client;

/** The server answered with a JSON-RPC error, an HTTP error, or something that is not a tools/call result. */
final class McpCallFailed extends \RuntimeException
{
    public static function jsonRpc(int $code, string $message): self
    {
        return new self(\sprintf('MCP error %d: %s', $code, $message), $code);
    }

    public static function http(int $status, string $body): self
    {
        return new self(\sprintf('HTTP %d: %s', $status, mb_substr($body, 0, 200)), $status);
    }

    public static function malformed(string $why, ?\Throwable $previous = null): self
    {
        return new self('Malformed MCP response: ' . $why, 0, $previous);
    }
}
