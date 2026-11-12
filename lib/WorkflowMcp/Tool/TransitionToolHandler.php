<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Tool;

use Leash\HumanInput\Policy\ApprovalPolicyInterface;
use Leash\HumanInput\RequestState\RequestStateRejected;
use Leash\HumanInput\Server\HumanInputGate;
use Leash\HumanInput\Server\Verdict;
use Leash\WorkflowMcp\Capability\ScopedCapabilityProvider;
use Leash\WorkflowMcp\Capability\VisibleTool;
use Leash\WorkflowMcp\Event\ApprovalDeclined;
use Leash\WorkflowMcp\Event\CapabilityDenied;
use Leash\WorkflowMcp\Event\ToolCalled;
use Leash\WorkflowMcp\Metadata\WorkflowMetadata;
use Leash\WorkflowMcp\Subject\SubjectProviderInterface;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Result\InputRequiredResult;
use Mcp\Server\RequestContext;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Runs a transition tool: re-check visibility, input DTO (Serializer + Validator), re-check `can()`,
 * ask a human first when the transition has `mcp_approval` (HumanInput, MRTR),
 * apply with `['input' => $dto]` in the context, persist, return `{state, availableTools, message}`.
 */
final readonly class TransitionToolHandler
{
    public function __construct(
        private ScopedCapabilityProvider $capabilities,
        private InputMapper $inputs,
        private SubjectProviderInterface $subjects,
        private EventDispatcherInterface $dispatcher,
        private HumanInputGate $humanInput,
        #[AutowireLocator(ApprovalPolicyInterface::class)]
        private ContainerInterface $approvalPolicies,
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
        // A duplicate retry gets the stored result, even though the transition is no longer visible.
        $stored = null === $bound ? null : $this->humanInput->replay($bound);
        if (null !== $stored) {
            return ToolResults::fromStored($stored);
        }

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

        $nonce = null;
        $policy = $this->policy($workflow, $tool);
        if (null !== $policy && null !== $input) {
            $decision = $this->humanInput->decide($context, $bound, $policy, $subject, $input, $subjectRef, $toolName, $arguments, $callerId);
            switch ($decision->verdict) {
                case Verdict::InputRequired:
                    \assert(null !== $decision->inputRequired);

                    return $decision->inputRequired;
                case Verdict::Declined:
                    if (null !== $decision->reason) {
                        return $this->called($workflow, $subject, $caller, $toolName, $arguments, ToolResults::fromStored($this->declinedWithReason($workflow, $subject, $caller, $toolName, $decision->reason, $decision->nonce)));
                    }

                    return $this->called($workflow, $subject, $caller, $toolName, $arguments, ToolResults::transitionResult(\sprintf('Not approved: a human declined "%s"; nothing changed.', $toolName), $places, $names));
                case Verdict::Unanswered:
                    return $this->called($workflow, $subject, $caller, $toolName, $arguments, ToolResults::transitionResult(\sprintf('No answer: "%s" needs human approval that was not given; nothing changed.', $toolName), $places, $names));
                case Verdict::Proceed:
                    $input = $decision->input;
                    $nonce = $decision->nonce;
            }
        }

        $result = $this->humanInput->apply($nonce, function () use ($workflow, $subject, $caller, $transition, $input, $toolName): array {
            $workflow->apply($subject, $transition, ['input' => $input]);
            $this->subjects->save($subject);

            $after = array_map(static fn(VisibleTool $t): string => $t->name, $this->capabilities->visibleTools($workflow, $subject, $caller));
            $placesAfter = array_map(strval(...), array_keys($workflow->getMarking($subject)->getPlaces()));

            return ['state' => implode(', ', $placesAfter), 'availableTools' => $after, 'message' => \sprintf('Applied "%s".', $toolName)];
        });

        return $this->called($workflow, $subject, $caller, $toolName, $arguments, ToolResults::fromStored($result));
    }

    /**
     * Once per request state: listeners may record the reason on the subject (saved), the transition is not applied.
     *
     * @return array<string, mixed>
     */
    private function declinedWithReason(WorkflowInterface $workflow, object $subject, UserInterface $caller, string $toolName, string $reason, ?string $nonce): array
    {
        return $this->humanInput->apply($nonce, function () use ($workflow, $subject, $caller, $toolName, $reason): array {
            $this->dispatcher->dispatch(new ApprovalDeclined($workflow->getName(), $subject, $caller, $toolName, $reason));
            $this->subjects->save($subject);

            $after = array_map(static fn(VisibleTool $t): string => $t->name, $this->capabilities->visibleTools($workflow, $subject, $caller));
            $places = array_map(strval(...), array_keys($workflow->getMarking($subject)->getPlaces()));

            return ['state' => implode(', ', $places), 'availableTools' => $after, 'message' => \sprintf('Not approved: a human declined "%s": %s The transition was not applied.', $toolName, $reason)];
        });
    }

    private function policy(WorkflowInterface $workflow, VisibleTool $tool): ?ApprovalPolicyInterface
    {
        $name = null === $tool->transition ? null : WorkflowMetadata::transitionApproval($workflow, $tool->transition);
        if (null === $name) {
            return null;
        }
        $policy = $this->approvalPolicies->has($name) ? $this->approvalPolicies->get($name) : null;
        if (!$policy instanceof ApprovalPolicyInterface || null === $tool->inputClass) {
            throw new \LogicException(\sprintf('Workflow "%s": mcp_approval "%s" of "%s" needs a registered ApprovalPolicyInterface and an mcp_input class.', $workflow->getName(), $name, $tool->name));
        }

        return $policy;
    }

    /** @param array<string, mixed> $arguments */
    private function called(WorkflowInterface $workflow, object $subject, UserInterface $caller, string $tool, array $arguments, CallToolResult $result): CallToolResult
    {
        $this->dispatcher->dispatch(new ToolCalled($workflow->getName(), $subject, $caller, $tool, $arguments, $result->isError));

        return $result;
    }
}
