<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\PlaceResource;

/**
 * Marks a PlaceResourceInterface service as the resource `$name` of workflow `$workflow`, served at `$uri`.
 * A place exposes it through its `mcp_resources` metadata (name => roles). `{subjectId}` in `$uri` is replaced
 * by the endpoint's subject id, so URIs stay unique across subjects (`game://{subjectId}/log`).
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
