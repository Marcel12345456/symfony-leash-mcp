<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\PlaceResource;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Finds place resources by workflow and resource name, reading #[AsPlaceResource] from each tagged service. */
final class PlaceResourceRegistry
{
    /** @var array<string, array{AsPlaceResource, PlaceResourceInterface}>|null */
    private ?array $resources = null;

    /** @param iterable<PlaceResourceInterface> $placeResources */
    public function __construct(
        #[AutowireIterator(PlaceResourceInterface::TAG)]
        private readonly iterable $placeResources,
    ) {}

    /** @return array{AsPlaceResource, PlaceResourceInterface} */
    public function get(string $workflow, string $name): array
    {
        return $this->all()[$workflow . '|' . $name]
            ?? throw new \LogicException(\sprintf('Workflow "%s" names place resource "%s" in mcp_resources, but no #[AsPlaceResource] service provides it.', $workflow, $name));
    }

    /** @return array<string, array{AsPlaceResource, PlaceResourceInterface}> */
    private function all(): array
    {
        if (null !== $this->resources) {
            return $this->resources;
        }

        $this->resources = [];
        foreach ($this->placeResources as $resource) {
            $attribute = (new \ReflectionClass($resource))->getAttributes(AsPlaceResource::class)[0]
                ?? throw new \LogicException(\sprintf('Place resource %s needs the #[AsPlaceResource] attribute.', $resource::class));
            $meta = $attribute->newInstance();
            $this->resources[$meta->workflow . '|' . $meta->name] = [$meta, $resource];
        }

        return $this->resources;
    }
}
