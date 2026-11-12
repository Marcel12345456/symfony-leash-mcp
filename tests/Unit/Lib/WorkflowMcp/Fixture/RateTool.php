<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Leash\WorkflowMcp\PlaceTool\AsPlaceTool;
use Leash\WorkflowMcp\PlaceTool\InputChoicesInterface;
use Leash\WorkflowMcp\PlaceTool\PlaceToolInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Synthetic acting place tool with required fields and a dynamic pick list (decisions/035). */
#[AsPlaceTool(workflow: 'document', name: 'doc_rate', description: 'Rate the document.', readOnly: false, input: RateInput::class)]
final class RateTool implements PlaceToolInterface, InputChoicesInterface
{
    /** @var list<RateInput> */
    public array $ratings = [];

    public function __invoke(object $subject, ?object $input, UserInterface $caller): array
    {
        \assert($input instanceof RateInput);
        $this->ratings[] = $input;

        return ['rated' => $input->stars, 'by' => $caller->getUserIdentifier()];
    }

    public function inputChoices(object $subject, UserInterface $caller): array
    {
        return ['stars' => ['3' => 'three stars', '4' => 'four stars', '5' => 'five stars']];
    }
}
