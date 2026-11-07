<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Capability;

use Leash\WorkflowMcp\PlaceTool\PlaceToolInterface;
use Symfony\Component\Workflow\Transition;

/** One tool the caller may use right now: either a transition tool or a place tool. */
final readonly class VisibleTool
{
    /** @param class-string|null $inputClass */
    private function __construct(
        public string $name,
        public string $description,
        public bool $readOnly,
        public ?string $inputClass,
        public ?Transition $transition,
        public ?PlaceToolInterface $placeTool,
    ) {}

    /** @param class-string|null $inputClass */
    public static function forTransition(string $name, string $description, ?string $inputClass, Transition $transition): self
    {
        return new self($name, $description, false, $inputClass, $transition, null);
    }

    /** @param class-string|null $inputClass */
    public static function forPlace(string $name, string $description, bool $readOnly, ?string $inputClass, PlaceToolInterface $tool): self
    {
        return new self($name, $description, $readOnly, $inputClass, null, $tool);
    }
}
