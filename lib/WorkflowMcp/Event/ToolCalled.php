<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Event;

use Symfony\Component\Security\Core\User\UserInterface;

/** A visible tool ran (successfully or with an actionable error). */
final readonly class ToolCalled
{
    /** @param array<string, mixed> $arguments */
    public function __construct(
        public string $workflow,
        public object $subject,
        public UserInterface $caller,
        public string $tool,
        public array $arguments,
        public bool $isError,
    ) {}
}
