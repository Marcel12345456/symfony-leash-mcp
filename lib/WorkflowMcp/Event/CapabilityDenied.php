<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Event;

use Symfony\Component\Security\Core\User\UserInterface;

/** A caller asked for a tool that is not visible to it right now. Nothing changed. */
final readonly class CapabilityDenied
{
    /**
     * @param list<string> $places    current places of the subject
     * @param list<string> $available tools visible to the caller
     */
    public function __construct(
        public string $workflow,
        public object $subject,
        public UserInterface $caller,
        public string $tool,
        public array $places,
        public array $available,
    ) {}
}
