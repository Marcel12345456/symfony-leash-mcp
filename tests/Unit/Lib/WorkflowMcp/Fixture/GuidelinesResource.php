<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Leash\WorkflowMcp\PlaceResource\AsPlaceResource;
use Leash\WorkflowMcp\PlaceResource\PlaceResourceInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsPlaceResource(workflow: 'document', name: 'doc_guidelines', uri: 'doc://guidelines', description: 'Writing guidelines.', mimeType: 'text/markdown')]
final class GuidelinesResource implements PlaceResourceInterface
{
    public function __invoke(object $subject, UserInterface $caller): string
    {
        return '# Guidelines';
    }
}
