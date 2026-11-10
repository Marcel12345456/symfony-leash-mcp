<?php

declare(strict_types=1);

namespace App\Werewolf\Mcp\Tool;

use App\Werewolf\Mcp\Input\TargetInput;
use App\Werewolf\Mcp\SeatAction;
use App\Werewolf\Rules\Action\ChooseVictim;
use Leash\WorkflowMcp\PlaceTool\AsPlaceTool;
use Leash\WorkflowMcp\PlaceTool\PlaceToolInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsPlaceTool(workflow: 'werewolf_game', name: 'ww_choose_victim', description: 'Night, wolves only: choose the seat the pack kills tonight (a living non-wolf seat).', readOnly: false, input: TargetInput::class)]
final readonly class ChooseVictimTool implements PlaceToolInterface
{
    public function __invoke(object $subject, ?object $input, UserInterface $caller): array
    {
        \assert($input instanceof TargetInput);

        return SeatAction::submit($subject, $caller, static fn(int $seat): ChooseVictim => new ChooseVictim($seat, $input->target));
    }
}
