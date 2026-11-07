<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Capability;

use Leash\WorkflowMcp\Metadata\WorkflowMetadata;
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
}
