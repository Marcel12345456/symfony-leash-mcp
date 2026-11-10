<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Leash\WorkflowMcp\PlaceResource\AsPlaceResource;
use Leash\WorkflowMcp\PlaceResource\PlaceResourceInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Editor-only: the author never sees the rubric. Shows the caller, so tests can see it is passed. */
#[AsPlaceResource(workflow: 'document', name: 'doc_rubric', uri: 'doc://{subjectId}/rubric', description: 'Review rubric.')]
final class RubricResource implements PlaceResourceInterface
{
    public function __invoke(object $subject, UserInterface $caller): array
    {
        return ['criteria' => ['clarity', 'accuracy'], 'reader' => $caller->getUserIdentifier()];
    }
}
