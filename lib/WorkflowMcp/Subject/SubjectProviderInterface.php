<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Subject;

use Symfony\Component\Workflow\WorkflowInterface;

/** Loads and persists the subject an MCP endpoint `/mcp/{workflow}/{subjectId}` works on. */
interface SubjectProviderInterface
{
    public function find(WorkflowInterface $workflow, string $id): ?object;

    public function save(object $subject): void;
}
