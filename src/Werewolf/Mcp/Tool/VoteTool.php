<?php

declare(strict_types=1);

namespace App\Werewolf\Mcp\Tool;

use App\Werewolf\Mcp\Input\TargetInput;
use App\Werewolf\Mcp\SeatAction;
use App\Werewolf\Rules\Action\Vote;
use Leash\WorkflowMcp\PlaceTool\AsPlaceTool;
use Leash\WorkflowMcp\PlaceTool\PlaceToolInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsPlaceTool(workflow: 'werewolf_game', name: 'ww_vote', description: 'Day vote: vote to eliminate a living seat (not yourself). Plurality is eliminated; a tie eliminates nobody.', readOnly: false, input: TargetInput::class)]
final readonly class VoteTool implements PlaceToolInterface
{
    public function __invoke(object $subject, ?object $input, UserInterface $caller): array
    {
        \assert($input instanceof TargetInput);

        return SeatAction::submit($subject, $caller, static fn(int $seat): Vote => new Vote($seat, $input->target));
    }
}
