<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Capability;

use Leash\WorkflowMcp\PlaceResource\PlaceResourceInterface;

/** One resource the caller may read right now. */
final readonly class VisibleResource
{
    public function __construct(
        public string $name,
        public string $uri,
        public string $description,
        public string $mimeType,
        public PlaceResourceInterface $resource,
    ) {}
}
