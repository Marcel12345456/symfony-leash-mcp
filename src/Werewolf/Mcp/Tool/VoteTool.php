<?php

declare(strict_types=1);

namespace App\Werewolf\Mcp\Tool;

use App\Werewolf\Entity\WerewolfGame;
use App\Werewolf\Mcp\Input\VoteInput;
use App\Werewolf\Mcp\SeatAction;
use App\Werewolf\Rules\Action\Vote;
use App\Werewolf\Security\WerewolfCallers;
use Leash\WorkflowMcp\PlaceTool\AsPlaceTool;
use Leash\WorkflowMcp\PlaceTool\InputChoicesInterface;
use Leash\WorkflowMcp\PlaceTool\PlaceToolInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsPlaceTool(workflow: 'werewolf_game', name: 'ww_vote', description: 'Day vote: vote to eliminate a living seat (not yourself), with a short public reason. Plurality is eliminated; a tie eliminates nobody.', readOnly: false, input: VoteInput::class)]
final readonly class VoteTool implements PlaceToolInterface, InputChoicesInterface
{
    public function __invoke(object $subject, ?object $input, UserInterface $caller): array
    {
        \assert($input instanceof VoteInput);

        return SeatAction::submit($subject, $caller, static fn(int $seat): Vote => new Vote($seat, $input->target, $input->reason));
    }

    /** A human seat picks its target from the other living seats. */
    public function inputChoices(object $subject, UserInterface $caller): array
    {
        \assert($subject instanceof WerewolfGame);
        $self = WerewolfCallers::seatOf($caller);
        $targets = [];
        foreach ($subject->game()->seats() as $seat) {
            if ($seat->alive && $seat->number !== $self) {
                $targets[$seat->number] = $seat->label();
            }
        }

        return ['target' => $targets];
    }
}
