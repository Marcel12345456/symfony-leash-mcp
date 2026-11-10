<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Tool;

use Leash\WorkflowMcp\Capability\ScopedCapabilityProvider;
use Leash\WorkflowMcp\Capability\VisibleTool;
use Leash\WorkflowMcp\Event\CapabilityDenied;
use Leash\WorkflowMcp\Event\ToolCalled;
use Leash\WorkflowMcp\Subject\SubjectProviderInterface;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/** Runs a place tool: re-check visibility, input DTO, invoke; non-read-only tools persist the subject. */
final readonly class PlaceToolHandler
{
    public function __construct(
        private ScopedCapabilityProvider $capabilities,
        private InputMapper $inputs,
        private SubjectProviderInterface $subjects,
        private EventDispatcherInterface $dispatcher,
    ) {}

    /** @param array<string, mixed> $arguments */
    public function handle(WorkflowInterface $workflow, object $subject, UserInterface $caller, string $toolName, array $arguments): CallToolResult
    {
        $visible = $this->capabilities->visibleTools($workflow, $subject, $caller);
        $names = array_map(static fn(VisibleTool $t): string => $t->name, $visible);
        $tool = array_find($visible, static fn(VisibleTool $t): bool => $t->name === $toolName && null !== $t->placeTool);

        if (null === $tool?->placeTool) {
            $places = array_map(strval(...), array_keys($workflow->getMarking($subject)->getPlaces()));
            $this->dispatcher->dispatch(new CapabilityDenied($workflow->getName(), $subject, $caller, $toolName, $places, $names));

            return ToolResults::unavailable($toolName, $places, $names);
        }

        try {
            $output = ($tool->placeTool)($subject, $this->inputs->map($tool->inputClass, $arguments), $caller);
            if (!$tool->readOnly) {
                $this->subjects->save($subject);
            }
            $result = \is_array($output)
                ? new CallToolResult([new TextContent($output)], false, $output)
                : new CallToolResult([new TextContent($output)]);
        } catch (InvalidInputException $e) {
            $result = ToolResults::invalidInput($toolName, $e->violations, $names);
        } catch (ToolCallException $e) {
            $result = ToolResults::error($e->getMessage());
        }

        $this->dispatcher->dispatch(new ToolCalled($workflow->getName(), $subject, $caller, $toolName, $arguments, $result->isError));

        return $result;
    }
}
