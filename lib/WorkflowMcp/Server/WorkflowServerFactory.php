<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Server;

use Leash\WorkflowMcp\Capability\ScopedCapabilityProvider;
use Leash\WorkflowMcp\Capability\VisibleTool;
use Leash\WorkflowMcp\Schema\SchemaFactory;
use Leash\WorkflowMcp\Tool\PlaceToolHandler;
use Leash\WorkflowMcp\Tool\ToolResults;
use Leash\WorkflowMcp\Tool\TransitionToolHandler;
use Mcp\Schema\Enum\CacheScope;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server;
use Mcp\Server\Builder;
use Mcp\Server\RequestContext;
use Mcp\Server\Wire\CachePolicy;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Builds the MCP server for one request: only the tools visible to this caller on this subject are registered
 * (decisions/004, 010, 011). Transport, sessions and request state are the caller's concern.
 */
final readonly class WorkflowServerFactory
{
    public function __construct(
        private ScopedCapabilityProvider $capabilities,
        private SchemaFactory $schemas,
        private TransitionToolHandler $transitionTools,
        private PlaceToolHandler $placeTools,
        private EventDispatcherInterface $dispatcher,
    ) {}

    public function create(WorkflowInterface $workflow, object $subject, UserInterface $caller): Builder
    {
        $visible = $this->capabilities->visibleTools($workflow, $subject, $caller);
        $places = array_map(strval(...), array_keys($workflow->getMarking($subject)->getPlaces()));

        $builder = Server::builder()
            ->setServerInfo('leash-' . $workflow->getName(), '0.1')
            ->setCachePolicy(CachePolicy::default(0, CacheScope::Private))
            ->addRequestHandler(new UnavailableCapabilityResponder(
                $workflow->getName(),
                $subject,
                $caller,
                $places,
                array_map(static fn(VisibleTool $t): string => $t->name, $visible),
                $this->dispatcher,
            ));

        foreach ($visible as $tool) {
            $handler = null !== $tool->transition ? $this->transitionTools->handle(...) : $this->placeTools->handle(...);
            $builder->addTool(
                handler: static function (RequestContext $context) use ($handler, $workflow, $subject, $caller): CallToolResult {
                    $request = $context->getRequest();

                    return $request instanceof CallToolRequest
                        ? $handler($workflow, $subject, $caller, $request->name, $request->arguments)
                        : ToolResults::error('Expected tools/call.');
                },
                name: $tool->name,
                description: $tool->description,
                annotations: new ToolAnnotations(readOnlyHint: $tool->readOnly),
                inputSchema: $this->schemas->inputSchema($tool->inputClass),
                outputSchema: null !== $tool->transition ? $this->schemas->transitionOutputSchema() : null,
            );
        }

        return $builder;
    }
}
