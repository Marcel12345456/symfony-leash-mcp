<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Capability;

use Leash\WorkflowMcp\Metadata\WorkflowMetadata;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * G3: the workflow, not an LLM, decides who works next. Returns the roles that currently have at least one
 * visible acting (non-read-only) tool. Runs without a caller, so voter guards see no token (decisions/012).
 */
final readonly class ActorSelector
{
    public function __construct(private ScopedCapabilityProvider $capabilities) {}

    /** @return list<string> */
    public function select(WorkflowInterface $workflow, object $subject): array
    {
        $actors = [];
        foreach (WorkflowMetadata::allRoles($workflow) as $role) {
            foreach ($this->capabilities->visibleToolsForRoles($workflow, $subject, [$role]) as $tool) {
                if (!$tool->readOnly) {
                    $actors[] = $role;
                    break;
                }
            }
        }
        sort($actors);

        return $actors;
    }

    /**
     * Per-caller form, for subjects whose actors are individual callers (game seats): the callers that hold at least
     * one visible acting tool, through the workflow's role resolver. Keeps the given order.
     *
     * @template T of UserInterface
     *
     * @param list<T> $callers
     *
     * @return list<T>
     */
    public function actorsAmong(WorkflowInterface $workflow, object $subject, array $callers): array
    {
        return array_values(array_filter(
            $callers,
            fn(UserInterface $caller): bool => [] !== array_filter(
                $this->capabilities->visibleTools($workflow, $subject, $caller),
                static fn(VisibleTool $tool): bool => !$tool->readOnly,
            ),
        ));
    }
}
