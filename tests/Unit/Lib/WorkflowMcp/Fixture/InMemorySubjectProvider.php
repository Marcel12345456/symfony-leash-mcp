<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Leash\WorkflowMcp\Subject\SubjectProviderInterface;
use Symfony\Component\Workflow\WorkflowInterface;

final class InMemorySubjectProvider implements SubjectProviderInterface
{
    public int $saves = 0;

    /** @param array<string, object> $subjects */
    public function __construct(private array $subjects = []) {}

    public function find(WorkflowInterface $workflow, string $id): ?object
    {
        return $this->subjects[$id] ?? null;
    }

    public function save(object $subject): void
    {
        ++$this->saves;
    }
}
