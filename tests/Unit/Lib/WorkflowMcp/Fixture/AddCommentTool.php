<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Symfony\Component\Security\Core\User\UserInterface;
use Leash\WorkflowMcp\PlaceTool\AsPlaceTool;
use Leash\WorkflowMcp\PlaceTool\PlaceToolInterface;

#[AsPlaceTool(workflow: 'document', name: 'doc_add_comment', description: 'Add a review comment.', readOnly: false)]
final class AddCommentTool implements PlaceToolInterface
{
    public function __invoke(object $subject, ?object $input, UserInterface $caller): string
    {
        return 'comment added';
    }
}
