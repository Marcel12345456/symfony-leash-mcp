<?php

declare(strict_types=1);

namespace App\Werewolf\Mcp\Tool;

use App\Werewolf\Mcp\Input\TargetInput;
use App\Werewolf\Mcp\SeatAction;
use App\Werewolf\Rules\Action\Inspect;
use Leash\WorkflowMcp\PlaceTool\AsPlaceTool;
use Leash\WorkflowMcp\PlaceTool\PlaceToolInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsPlaceTool(workflow: 'werewolf_game', name: 'ww_inspect', description: 'Night, seer only: learn whether a living seat is a wolf. The answer appears in the seer-notes resource.', readOnly: false, input: TargetInput::class)]
final readonly class InspectTool implements PlaceToolInterface
{
    public function __invoke(object $subject, ?object $input, UserInterface $caller): array
    {
        \assert($input instanceof TargetInput);

        return SeatAction::submit($subject, $caller, static fn(int $seat): Inspect => new Inspect($seat, $input->target));
    }
}
