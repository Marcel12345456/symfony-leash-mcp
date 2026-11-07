<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\PlaceTool;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Finds place tools by workflow and tool name, reading #[AsPlaceTool] from each tagged service. */
final class PlaceToolRegistry
{
    /** @var array<string, array{AsPlaceTool, PlaceToolInterface}>|null */
    private ?array $tools = null;

    /** @param iterable<PlaceToolInterface> $placeTools */
    public function __construct(
        #[AutowireIterator(PlaceToolInterface::TAG)]
        private readonly iterable $placeTools,
    ) {}

    /** @return array{AsPlaceTool, PlaceToolInterface} */
    public function get(string $workflow, string $name): array
    {
        return $this->all()[$workflow . '|' . $name]
            ?? throw new \LogicException(\sprintf('Workflow "%s" names place tool "%s" in mcp_tools, but no #[AsPlaceTool] service provides it.', $workflow, $name));
    }

    /** @return array<string, array{AsPlaceTool, PlaceToolInterface}> */
    private function all(): array
    {
        if (null !== $this->tools) {
            return $this->tools;
        }

        $this->tools = [];
        foreach ($this->placeTools as $tool) {
            $attribute = (new \ReflectionClass($tool))->getAttributes(AsPlaceTool::class)[0]
                ?? throw new \LogicException(\sprintf('Place tool %s needs the #[AsPlaceTool] attribute.', $tool::class));
            $meta = $attribute->newInstance();
            $this->tools[$meta->workflow . '|' . $meta->name] = [$meta, $tool];
        }

        return $this->tools;
    }
}
