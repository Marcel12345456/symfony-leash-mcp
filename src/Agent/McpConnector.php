<?php

declare(strict_types=1);

namespace App\Agent;

use Mcp\Client;
use Mcp\Client\Transport\HttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Connects an MCP client to one of our endpoints as one caller (bearer token, decisions/005). */
final readonly class McpConnector
{
    public function __construct(
        private ClientInterface $http,
        #[Autowire('%env(LEASH_MCP_BASE_URL)%')]
        private string $baseUrl,
    ) {}

    /** @param string $path e.g. `/mcp/werewolf_game/g1` */
    public function connect(string $path, #[\SensitiveParameter] string $token, string $clientName = 'leash-agent'): Client
    {
        $factory = new Psr17Factory();
        $client = Client::builder()->setClientInfo($clientName, '0.1')->build();
        $client->connect(new HttpTransport(rtrim($this->baseUrl, '/') . $path, ['Authorization' => 'Bearer ' . $token], $this->http, $factory, $factory));

        return $client;
    }
}
