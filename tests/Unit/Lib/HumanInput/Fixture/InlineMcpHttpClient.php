<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\HumanInput\Fixture;

use Mcp\Server\Builder;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client that hands the raw client's POST body to an in-process stateless server (decisions/014).
 * The bearer token picks the server (one per caller); every token sent is recorded.
 */
final class InlineMcpHttpClient implements ClientInterface
{
    /** @var list<string> */
    public array $tokens = [];

    /** @param \Closure(string): Builder $serverForToken */
    public function __construct(private readonly \Closure $serverForToken) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        \assert('2026-07-28' === $request->getHeaderLine('MCP-Protocol-Version'), 'The SDK HeaderFactory sets the protocol header.');
        $this->tokens[] = $token = substr($request->getHeaderLine('Authorization'), \strlen('Bearer '));
        $json = ($this->serverForToken)($token)->buildStateless()->handleInline((string) $request->getBody())->toJson();

        return new Response(200, ['Content-Type' => 'application/json'], $json);
    }
}
