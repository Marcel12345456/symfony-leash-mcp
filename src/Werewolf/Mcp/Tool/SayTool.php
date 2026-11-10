<?php

declare(strict_types=1);

namespace App\Werewolf\Mcp\Tool;

use App\Werewolf\Mcp\Input\SayInput;
use App\Werewolf\Mcp\SeatAction;
use App\Werewolf\Rules\Action\Say;
use Leash\WorkflowMcp\PlaceTool\AsPlaceTool;
use Leash\WorkflowMcp\PlaceTool\PlaceToolInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsPlaceTool(workflow: 'werewolf_game', name: 'ww_say', description: 'Day discussion, your turn: make one public statement (max 300 characters).', readOnly: false, input: SayInput::class)]
final readonly class SayTool implements PlaceToolInterface
{
    public function __invoke(object $subject, ?object $input, UserInterface $caller): array
    {
        \assert($input instanceof SayInput);

        return SeatAction::submit($subject, $caller, static fn(int $seat): Say => new Say($seat, $input->message));
    }
}
