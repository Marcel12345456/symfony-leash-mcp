<?php

declare(strict_types=1);

namespace App\Spike;

use Mcp\Schema\Elicitation\BooleanSchemaDefinition;
use Mcp\Schema\Elicitation\ElicitationSchema;
use Mcp\Schema\Enum\ElicitAction;
use Mcp\Schema\Request\ElicitRequest;
use Mcp\Schema\Result\InputRequiredResult;
use Mcp\Server;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Phase 0 Proof 1 (spike, not library code): an MCP server built per HTTP request.
 * The tool set depends on the subject: "open" exposes spike_ask, any other subject does not.
 * spike_ask answers with input_required first and completes on the retry (MRTR, decisions/001).
 */
final readonly class SpikeMcpController
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
        #[Autowire('%kernel.cache_dir%/mcp-spike-sessions')]
        private string $sessionDir,
    ) {}

    #[Route('/mcp-spike/{subjectId}', name: 'spike_mcp', methods: ['GET', 'POST', 'DELETE', 'OPTIONS'])]
    public function __invoke(Request $request, string $subjectId): Response
    {
        $builder = Server::builder()
            ->setServerInfo('leash-spike', '0.1')
            ->setRequestState(hash('sha256', $this->secret . '|mcp-request-state'), 86400)
            ->setSession(new FileSessionStore($this->sessionDir))
            ->addResource(
                static fn(): string => \sprintf('subject %s is %s', $subjectId, 'open' === $subjectId ? 'open' : 'closed'),
                'spike://subject/' . $subjectId,
                'subject',
                mimeType: 'text/plain',
            );

        if ('open' === $subjectId) {
            $builder->addTool(
                static function (string $reason, RequestContext $context): string|InputRequiredResult {
                    $answer = $context->getInputContext()?->elicitResult('approve');
                    if (null === $answer) {
                        return new InputRequiredResult(
                            ['approve' => ElicitRequest::forForm(
                                'Approve: ' . $reason . '?',
                                new ElicitationSchema(['ok' => new BooleanSchemaDefinition('Approve')], ['ok']),
                            )],
                            $context->mintRequestState(['reason' => $reason]),
                        );
                    }

                    $approved = ElicitAction::Accept === $answer->action && true === ($answer->content['ok'] ?? null);

                    return ($approved ? 'approved: ' : 'declined: ') . $reason;
                },
                'spike_ask',
                description: 'Asks a human to approve something. Use when approval is required.',
                inputSchema: [
                    'type' => 'object',
                    'properties' => ['reason' => ['type' => 'string', 'description' => 'What to approve']],
                    'required' => ['reason'],
                ],
            );
        }

        $factory = new Psr17Factory();
        $psrRequest = (new PsrHttpFactory($factory, $factory, $factory, $factory))->createRequest($request);
        $psrResponse = $builder->build()->run(new StreamableHttpTransport($psrRequest, $factory, $factory));

        return (new HttpFoundationFactory())->createResponse(
            $psrResponse,
            'text/event-stream' === strtolower($psrResponse->getHeaderLine('Content-Type')),
        );
    }
}
