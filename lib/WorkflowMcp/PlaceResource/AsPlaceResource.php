<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\PlaceResource;

/**
 * Marks a PlaceResourceInterface service as the resource `$name` of workflow `$workflow`, served at `$uri`.
 * A place exposes it through its `mcp_resources` metadata (name => roles). The URI is relative to the
 * subject's endpoint, so it does not need to carry the subject id.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsPlaceResource
{
    public function __construct(
        public string $workflow,
        public string $name,
        public string $uri,
        public string $description,
        public string $mimeType = 'application/json',
    ) {}
}
