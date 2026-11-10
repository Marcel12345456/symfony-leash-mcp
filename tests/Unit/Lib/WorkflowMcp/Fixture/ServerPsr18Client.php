<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Mcp\Server\Builder;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client that runs a built server per request, so `Mcp\Client` + `HttpTransport` talk to it in-process.
 * One shared session store keeps the client's `initialize` session across requests (decisions/031).
 */
final class ServerPsr18Client implements ClientInterface
{
    private readonly InMemorySessionStore $sessions;
    private readonly Psr17Factory $factory;

    public function __construct(private readonly Builder $builder)
    {
        $this->sessions = new InMemorySessionStore();
        $this->factory = new Psr17Factory();
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $serverRequest = new ServerRequest($request->getMethod(), $request->getUri(), $request->getHeaders(), (string) $request->getBody(), serverParams: ['REMOTE_ADDR' => '127.0.0.1']);

        return $this->builder->setSession($this->sessions)->build()->run(new StreamableHttpTransport($serverRequest->withHeader('Host', 'localhost'), $this->factory, $this->factory));
    }
}
