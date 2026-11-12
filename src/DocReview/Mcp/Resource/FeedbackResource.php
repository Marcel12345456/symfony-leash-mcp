<?php

declare(strict_types=1);

namespace App\DocReview\Mcp\Resource;

use App\DocReview\Entity\Document;
use Leash\WorkflowMcp\PlaceResource\AsPlaceResource;
use Leash\WorkflowMcp\PlaceResource\PlaceResourceInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsPlaceResource(workflow: 'doc_review', name: 'feedback', uri: 'doc://{subjectId}/feedback', description: 'Feedback from the reviewer and the editor, newest last. Address every point in your next draft.')]
final readonly class FeedbackResource implements PlaceResourceInterface
{
    public function __invoke(object $subject, UserInterface $caller): array
    {
        \assert($subject instanceof Document);

        return ['feedback' => $subject->feedback()];
    }
}
