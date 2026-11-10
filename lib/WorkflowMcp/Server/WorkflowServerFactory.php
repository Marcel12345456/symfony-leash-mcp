<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Server;

use Leash\HumanInput\Server\HumanInputGate;
use Leash\WorkflowMcp\Capability\ScopedCapabilityProvider;
use Leash\WorkflowMcp\Capability\VisibleTool;
use Leash\WorkflowMcp\Schema\SchemaFactory;
use Leash\WorkflowMcp\Tool\PlaceToolHandler;
use Leash\WorkflowMcp\Tool\ToolResults;
use Leash\WorkflowMcp\Tool\TransitionToolHandler;
use Mcp\Schema\Enum\CacheScope;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\InputRequiredResult;
use Mcp\Schema\ServerCapabilities;
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
 * (decisions/004, 010, 011). Transport, sessions and the request-state key are the caller's concern.
 */
final readonly class WorkflowServerFactory
{
    public function __construct(
        private ScopedCapabilityProvider $capabilities,
        private SchemaFactory $schemas,
        private TransitionToolHandler $transitionTools,
        private PlaceToolHandler $placeTools,
        private EventDispatcherInterface $dispatcher,
        private HumanInputGate $humanInput,
    ) {}

    /** @param string $subjectId binds MRTR request states to this subject (with the workflow name) */
    public function create(WorkflowInterface $workflow, object $subject, UserInterface $caller, string $subjectId): Builder
    {
        $subjectRef = $workflow->getName() . ':' . $subjectId;
        $visible = $this->capabilities->visibleTools($workflow, $subject, $caller);
        $places = array_map(strval(...), array_keys($workflow->getMarking($subject)->getPlaces()));

        $builder = Server::builder()
            ->setServerInfo('leash-' . $workflow->getName(), '0.1')
            ->setCachePolicy(CachePolicy::default(0, CacheScope::Private))
            // Same capabilities for every caller, so an empty tool list still shows as one (decisions/026).
            ->setCapabilities(new ServerCapabilities(
                tools: true,
                toolsListChanged: false,
                resources: true,
                resourcesSubscribe: false,
                resourcesListChanged: false,
                prompts: false,
                promptsListChanged: false,
                logging: true,
                completions: true,
            ))
            ->addRequestHandler(new UnavailableCapabilityResponder(
                $workflow->getName(),
                $subject,
                $caller,
                $places,
                array_map(static fn(VisibleTool $t): string => $t->name, $visible),
                $this->dispatcher,
                $this->humanInput,
                $subjectRef,
            ));

        foreach ($visible as $tool) {
            $transitionTools = $this->transitionTools;
            $placeTools = $this->placeTools;
            $isTransition = null !== $tool->transition;
            $builder->addTool(
                handler: static function (RequestContext $context) use ($transitionTools, $placeTools, $isTransition, $workflow, $subject, $caller, $subjectRef): CallToolResult|InputRequiredResult {
                    $request = $context->getRequest();
                    if (!$request instanceof CallToolRequest) {
                        return ToolResults::error('Expected tools/call.');
                    }

                    return $isTransition
                        ? $transitionTools->handle($workflow, $subject, $caller, $request->name, $request->arguments, $context, $subjectRef)
                        : $placeTools->handle($workflow, $subject, $caller, $request->name, $request->arguments);
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
