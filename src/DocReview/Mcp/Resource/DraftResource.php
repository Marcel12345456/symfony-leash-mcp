<?php

declare(strict_types=1);

namespace App\DocReview\Mcp\Resource;

use App\DocReview\Entity\Document;
use Leash\WorkflowMcp\PlaceResource\AsPlaceResource;
use Leash\WorkflowMcp\PlaceResource\PlaceResourceInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsPlaceResource(workflow: 'doc_review', name: 'draft', uri: 'doc://{subjectId}/draft', description: 'The current draft (empty before the first submission) and, once published, its headline.')]
final readonly class DraftResource implements PlaceResourceInterface
{
    public function __invoke(object $subject, UserInterface $caller): array
    {
        \assert($subject instanceof Document);

        return ['status' => $subject->getStatus(), 'title' => $subject->title(), 'body' => $subject->body(), 'headline' => $subject->headline()];
    }
}
