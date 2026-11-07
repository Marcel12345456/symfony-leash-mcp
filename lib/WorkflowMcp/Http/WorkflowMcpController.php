<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Http;

use Leash\WorkflowMcp\Server\WorkflowServerFactory;
use Leash\WorkflowMcp\Subject\SubjectProviderInterface;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * MCP endpoint per subject (PLAN §3.2): the URL names workflow and subject, the bearer token the caller.
 * A server with only the caller's visible tools is built for every request (decisions/004, 015).
 */
final readonly class WorkflowMcpController
{
    public function __construct(
        #[AutowireLocator('workflow', indexAttribute: 'name')]
        private ContainerInterface $workflows,
        private SubjectProviderInterface $subjects,
        private WorkflowServerFactory $servers,
        #[Autowire('%kernel.cache_dir%/mcp-sessions')]
        private string $sessionDir,
    ) {}

    #[Route('/mcp/{workflow}/{subjectId}', name: 'leash_workflow_mcp', methods: ['GET', 'POST', 'DELETE', 'OPTIONS'])]
    public function __invoke(Request $request, string $workflow, string $subjectId, #[CurrentUser] ?UserInterface $caller): Response
    {
        if (null === $caller) {
            return new JsonResponse(['error' => 'Send a bearer token.'], Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);
        }

        $definition = $this->workflows->has($workflow) ? $this->workflows->get($workflow) : null;
        if (!$definition instanceof WorkflowInterface) {
            return new JsonResponse(['error' => \sprintf('Unknown workflow "%s".', $workflow)], Response::HTTP_NOT_FOUND);
        }

        $subject = $this->subjects->find($definition, $subjectId);
        if (null === $subject) {
            return new JsonResponse(['error' => \sprintf('No %s subject "%s".', $workflow, $subjectId)], Response::HTTP_NOT_FOUND);
        }

        // Legacy (initialize-based) clients need a persistent session; the stateless wire ignores it (decisions/014).
        $builder = $this->servers->create($definition, $subject, $caller)->setSession(new FileSessionStore($this->sessionDir));

        $factory = new Psr17Factory();
        $psrRequest = (new PsrHttpFactory($factory, $factory, $factory, $factory))->createRequest($request);
        $psrResponse = $builder->build()->run(new StreamableHttpTransport($psrRequest, $factory, $factory));

        return (new HttpFoundationFactory())->createResponse(
            $psrResponse,
            'text/event-stream' === strtolower($psrResponse->getHeaderLine('Content-Type')),
        );
    }
}
