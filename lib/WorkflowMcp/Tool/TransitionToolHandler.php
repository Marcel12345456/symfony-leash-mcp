<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Tool;

use Leash\WorkflowMcp\Capability\ScopedCapabilityProvider;
use Leash\WorkflowMcp\Capability\VisibleTool;
use Leash\WorkflowMcp\Event\CapabilityDenied;
use Leash\WorkflowMcp\Event\ToolCalled;
use Leash\WorkflowMcp\Subject\SubjectProviderInterface;
use Mcp\Schema\Result\CallToolResult;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Runs a transition tool: re-check visibility, input DTO (Serializer + Validator), re-check `can()`,
 * apply with `['input' => $dto]` in the context, persist, return `{state, availableTools, message}`.
 */
final readonly class TransitionToolHandler
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
        $tool = array_find($visible, static fn(VisibleTool $t): bool => $t->name === $toolName && null !== $t->transition);
        $places = array_map(strval(...), array_keys($workflow->getMarking($subject)->getPlaces()));

        if (null === $tool?->transition) {
            $this->dispatcher->dispatch(new CapabilityDenied($workflow->getName(), $subject, $caller, $toolName, $places, $names));

            return ToolResults::unavailable($toolName, $places, $names);
        }

        $transition = $tool->transition->getName();
        try {
            $input = $this->inputs->map($tool->inputClass, $arguments);
        } catch (InvalidInputException $e) {
            return $this->called($workflow, $subject, $caller, $toolName, $arguments, ToolResults::invalidInput($toolName, $e->violations, $names));
        }

        $blockers = $workflow->buildTransitionBlockerList($subject, $transition);
        if (!$blockers->isEmpty()) {
            $reasons = implode(' ', array_map(static fn(TransitionBlocker $b): string => $b->getMessage(), iterator_to_array($blockers)));

            return $this->called($workflow, $subject, $caller, $toolName, $arguments, ToolResults::error(\sprintf('"%s" is blocked: %s', $toolName, $reasons)));
        }

        $workflow->apply($subject, $transition, ['input' => $input]);
        $this->subjects->save($subject);

        $after = array_map(static fn(VisibleTool $t): string => $t->name, $this->capabilities->visibleTools($workflow, $subject, $caller));
        $placesAfter = array_map(strval(...), array_keys($workflow->getMarking($subject)->getPlaces()));

        return $this->called($workflow, $subject, $caller, $toolName, $arguments, ToolResults::transitionResult(\sprintf('Applied "%s".', $toolName), $placesAfter, $after));
    }

    /** @param array<string, mixed> $arguments */
    private function called(WorkflowInterface $workflow, object $subject, UserInterface $caller, string $tool, array $arguments, CallToolResult $result): CallToolResult
    {
        $this->dispatcher->dispatch(new ToolCalled($workflow->getName(), $subject, $caller, $tool, $arguments, $result->isError));

        return $result;
    }
}
