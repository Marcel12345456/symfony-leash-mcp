<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\PlaceTool;

/**
 * Marks a PlaceToolInterface service as the tool `$name` of workflow `$workflow`.
 * A place exposes it through its `mcp_tools` metadata. `$readOnly` becomes the MCP readOnlyHint and
 * decides whether the tool counts as acting for ActorSelector (G3). `$input` is an optional DTO class.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsPlaceTool
{
    /** @param class-string|null $input */
    public function __construct(
        public string $workflow,
        public string $name,
        public string $description,
        public bool $readOnly = true,
        public ?string $input = null,
    ) {}
}
