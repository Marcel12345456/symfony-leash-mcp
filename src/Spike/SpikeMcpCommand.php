<?php

declare(strict_types=1);

namespace App\Spike;

use Mcp\Client;
use Mcp\Client\Handler\Request\ElicitationRequestHandler;
use Mcp\Client\Transport\HttpTransport;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Content\TextResourceContents;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\Process\Process;

/**
 * Phase 0 Proof 1: starts the app on php -S, then talks to SpikeMcpController with the mcp/sdk client
 * over Streamable HTTP. Exit 0 only if every check holds.
 */
#[AsCommand('spike:mcp', 'Proof 1: per-request MCP server, dynamic tool + resource, input_required round-trip')]
final readonly class SpikeMcpCommand
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {}

    public function __invoke(SymfonyStyle $io, #[Option] int $port = 8123): int
    {
        $server = new Process([\PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', 'public'], $this->projectDir, ['APP_ENV' => 'dev']);
        $server->start();

        try {
            $this->waitFor($port);
            $failures = $this->run($io, 'http://127.0.0.1:' . $port . '/mcp-spike/');
        } finally {
            $server->stop();
        }

        foreach ($failures as $failure) {
            $io->error($failure);
        }

        return [] === $failures ? Command::SUCCESS : Command::FAILURE;
    }

    /** @return list<string> */
    private function run(SymfonyStyle $io, string $baseUrl): array
    {
        $failures = [];

        $closed = $this->connect($baseUrl . 'closed', new SpikeElicitationCallback());
        $closedTools = array_map(static fn($tool): string => $tool->name, $closed->listTools()->tools);
        $closed->disconnect();
        $io->writeln('tools(closed): [' . implode(', ', $closedTools) . ']');
        if ([] !== $closedTools) {
            $failures[] = 'closed subject must expose no tools';
        }

        $callback = new SpikeElicitationCallback();
        $open = $this->connect($baseUrl . 'open', $callback);
        $openTools = array_map(static fn($tool): string => $tool->name, $open->listTools()->tools);
        $io->writeln('tools(open): [' . implode(', ', $openTools) . ']');
        if (['spike_ask'] !== $openTools) {
            $failures[] = 'open subject must expose exactly spike_ask';
        }

        $contents = $open->readResource('spike://subject/open')->contents[0] ?? null;
        $resource = $contents instanceof TextResourceContents ? $contents->text : null;
        $io->writeln('resource: ' . ($resource ?? '<none>'));
        if ('subject open is open' !== $resource) {
            $failures[] = 'resource content mismatch';
        }

        $content = $open->callTool('spike_ask', ['reason' => 'refund 1200 cents'])->content[0] ?? null;
        $result = $content instanceof TextContent && \is_string($content->text) ? $content->text : null;
        $open->disconnect();
        $io->writeln('elicitation asked: ' . ($callback->askedMessage ?? '<never>'));
        $io->writeln('tool result: ' . ($result ?? '<none>'));
        if ('Approve: refund 1200 cents?' !== $callback->askedMessage) {
            $failures[] = 'input_required was not round-tripped through the elicitation handler';
        }
        if ('approved: refund 1200 cents' !== $result) {
            $failures[] = 'tool did not complete after the retry';
        }

        return $failures;
    }

    private function connect(string $url, SpikeElicitationCallback $callback): Client
    {
        $factory = new Psr17Factory();
        $client = Client::builder()
            ->setClientInfo('leash-spike-cli', '0.1')
            ->setCapabilities(new ClientCapabilities(elicitation: true, elicitationForm: true))
            // mcp/sdk 0.9.0 types the parameter as RequestHandlerInterface<mixed>; its own handler is <ElicitResult> (BUGS.md).
            ->addRequestHandler(new ElicitationRequestHandler($callback)) // @phpstan-ignore argument.type
            ->build();
        $client->connect(new HttpTransport($url, [], new Psr18Client(null, $factory, $factory), $factory, $factory));

        return $client;
    }

    private function waitFor(int $port): void
    {
        for ($i = 0; $i < 50; ++$i) {
            $socket = @fsockopen('127.0.0.1', $port);
            if (false !== $socket) {
                fclose($socket);

                return;
            }
            usleep(100_000);
        }

        throw new \RuntimeException('php -S did not start on port ' . $port);
    }
}
