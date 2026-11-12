<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Tool;

use Leash\HumanInput\RequestState\RequestStateRejected;
use Leash\HumanInput\Server\HumanInputGate;
use Leash\WorkflowMcp\Capability\ScopedCapabilityProvider;
use Leash\WorkflowMcp\Capability\VisibleTool;
use Leash\WorkflowMcp\Event\CapabilityDenied;
use Leash\WorkflowMcp\Event\ToolCalled;
use Leash\WorkflowMcp\PlaceTool\InputChoicesInterface;
use Leash\WorkflowMcp\Schema\SchemaFactory;
use Leash\WorkflowMcp\Subject\SubjectProviderInterface;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\InputRequiredResult;
use Mcp\Server\RequestContext;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Runs a place tool: re-check visibility, ask a human-backed caller for missing required fields (decisions/035, MRTR),
 * input DTO, invoke; non-read-only tools persist the subject. An answered retry is applied once per request state.
 */
final readonly class PlaceToolHandler
{
    public function __construct(
        private ScopedCapabilityProvider $capabilities,
        private InputMapper $inputs,
        private SubjectProviderInterface $subjects,
        private EventDispatcherInterface $dispatcher,
        private HumanInputGate $humanInput,
        private ElicitMissingInputPolicy $missingInput,
        private SchemaFactory $schemas,
    ) {}

    /**
     * @param array<string, mixed> $arguments
     * @param string               $subjectRef what the request state is bound to (workflow + subject id)
     */
    public function handle(WorkflowInterface $workflow, object $subject, UserInterface $caller, string $toolName, array $arguments, ?RequestContext $context = null, string $subjectRef = ''): CallToolResult|InputRequiredResult
    {
        $callerId = $caller->getUserIdentifier();
        try {
            $bound = $this->humanInput->boundRetry($context?->getInputContext(), $subjectRef, $toolName, $arguments, $callerId);
        } catch (RequestStateRejected $e) {
            return $this->called($workflow, $subject, $caller, $toolName, $arguments, ToolResults::error($e->getMessage()));
        }
        $stored = null === $bound ? null : $this->humanInput->replay($bound);
        if (null !== $stored) {
            return self::result($stored['output'] ?? null);
        }

        $visible = $this->capabilities->visibleTools($workflow, $subject, $caller);
        $names = array_map(static fn(VisibleTool $t): string => $t->name, $visible);
        $tool = array_find($visible, static fn(VisibleTool $t): bool => $t->name === $toolName && null !== $t->placeTool);

        if (null === $tool?->placeTool) {
            $places = array_map(strval(...), array_keys($workflow->getMarking($subject)->getPlaces()));
            $this->dispatcher->dispatch(new CapabilityDenied($workflow->getName(), $subject, $caller, $toolName, $places, $names));

            return ToolResults::unavailable($toolName, $places, $names);
        }

        $schema = $this->schemas->inputSchema($tool->inputClass);
        $missing = $this->missingInput->missingFields($schema, $arguments);
        $humanBacked = $this->missingInput->isHumanBacked($this->capabilities->rolesFor($workflow, $subject, $caller));
        if ([] !== $missing && $humanBacked && null !== $context) {
            if (null === $bound) {
                $choices = $tool->placeTool instanceof InputChoicesInterface ? $tool->placeTool->inputChoices($subject, $caller) : [];

                return $this->humanInput->ask($context, ElicitMissingInputPolicy::KEY, $this->missingInput->inputRequest($toolName, $schema, $missing, $choices), $subjectRef, $toolName, $arguments, $callerId);
            }
            $answer = $this->humanInput->answer($context, ElicitMissingInputPolicy::KEY);
            if (null === $answer || !$answer->isAccepted()) {
                return $this->called($workflow, $subject, $caller, $toolName, $arguments, new CallToolResult([new TextContent(\sprintf('No answer: "%s" was not called; nothing changed.', $toolName))]));
            }
            $arguments = $this->missingInput->merge($schema, $arguments, $answer->content ?? []);
        }

        $placeTool = $tool->placeTool;
        $readOnly = $tool->readOnly;
        try {
            $input = $this->inputs->map($tool->inputClass, $arguments);
            $stored = $this->humanInput->apply($bound?->nonce, function () use ($placeTool, $readOnly, $subject, $input, $caller): array {
                $output = $placeTool($subject, $input, $caller);
                if (!$readOnly) {
                    $this->subjects->save($subject);
                }

                return ['output' => $output];
            });
            $result = self::result($stored['output'] ?? null);
        } catch (InvalidInputException $e) {
            $result = ToolResults::invalidInput($toolName, $e->violations, $names);
        } catch (ToolCallException $e) {
            $result = ToolResults::error($e->getMessage());
        }

        return $this->called($workflow, $subject, $caller, $toolName, $arguments, $result);
    }

    /** A tool's output (assoc array → structured content, string → text), fresh or replayed. */
    private static function result(mixed $output): CallToolResult
    {
        return match (true) {
            \is_array($output) => new CallToolResult([new TextContent($output)], false, $output),
            \is_string($output) => new CallToolResult([new TextContent($output)]),
            default => ToolResults::error('The stored result of this request is unreadable; call the tool again without inputResponses.'),
        };
    }

    /** @param array<string, mixed> $arguments */
    private function called(WorkflowInterface $workflow, object $subject, UserInterface $caller, string $tool, array $arguments, CallToolResult $result): CallToolResult
    {
        $this->dispatcher->dispatch(new ToolCalled($workflow->getName(), $subject, $caller, $tool, $arguments, $result->isError));

        return $result;
    }
}
