<?php

declare(strict_types=1);

namespace App\DocReview\Mcp\Resource;

use App\DocReview\Entity\Document;
use Leash\WorkflowMcp\PlaceResource\AsPlaceResource;
use Leash\WorkflowMcp\PlaceResource\PlaceResourceInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsPlaceResource(workflow: 'doc_review', name: 'brief', uri: 'doc://{subjectId}/brief', description: 'What this document must say and for whom.')]
final readonly class BriefResource implements PlaceResourceInterface
{
    public function __invoke(object $subject, UserInterface $caller): array
    {
        \assert($subject instanceof Document);

        return ['document' => $subject->getId(), 'status' => $subject->getStatus(), 'brief' => $subject->brief()];
    }
}
