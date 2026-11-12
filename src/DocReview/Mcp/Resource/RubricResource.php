<?php

declare(strict_types=1);

namespace App\DocReview\Mcp\Resource;

use Leash\WorkflowMcp\PlaceResource\AsPlaceResource;
use Leash\WorkflowMcp\PlaceResource\PlaceResourceInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** The reviewer's checklist; the author never sees it (same mechanism as the wolf den). */
#[AsPlaceResource(workflow: 'doc_review', name: 'rubric', uri: 'doc://{subjectId}/rubric', description: 'The review checklist. Approve only when every point holds.')]
final readonly class RubricResource implements PlaceResourceInterface
{
    public const array POINTS = [
        'Release notes have an "Upgrade notes" section that says what users must change.',
        'Every claim is concrete: name the feature, the version, the command.',
        'No marketing superlatives ("revolutionary", "blazing fast").',
        'The title says what changed, in at most 80 characters.',
    ];

    public function __invoke(object $subject, UserInterface $caller): array
    {
        return ['rubric' => self::POINTS];
    }
}
