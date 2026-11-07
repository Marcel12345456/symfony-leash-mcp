<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\PlaceTool;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A tool attached to a workflow place. Describe it with #[AsPlaceTool].
 * Returns structured content (assoc array) or plain text. Refuse with Mcp\Exception\ToolCallException.
 */
#[AutoconfigureTag(self::TAG)]
interface PlaceToolInterface
{
    public const string TAG = 'leash.workflow_mcp.place_tool';

    /** @return array<string, mixed>|string */
    public function __invoke(object $subject, ?object $input): array|string;
}
