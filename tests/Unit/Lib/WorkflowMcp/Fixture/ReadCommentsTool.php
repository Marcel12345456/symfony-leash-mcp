<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Leash\WorkflowMcp\PlaceTool\AsPlaceTool;
use Leash\WorkflowMcp\PlaceTool\PlaceToolInterface;

#[AsPlaceTool(workflow: 'document', name: 'doc_read_comments', description: 'Read review comments.')]
final class ReadCommentsTool implements PlaceToolInterface
{
    public function __invoke(object $subject, ?object $input): array
    {
        return ['comments' => []];
    }
}
