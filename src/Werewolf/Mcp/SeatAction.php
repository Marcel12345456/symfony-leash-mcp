<?php

declare(strict_types=1);

namespace App\Werewolf\Mcp;

use App\Werewolf\Entity\WerewolfGame;
use App\Werewolf\Rules\Action\Action;
use App\Werewolf\Security\WerewolfCallers;
use Mcp\Exception\ToolCallException;
use Symfony\Component\Security\Core\User\UserInterface;

/** Submits a seat's action to the rules engine; a rejection becomes an actionable tool error. */
final class SeatAction
{
    /**
     * @param \Closure(int): Action $action builds the action for the calling seat
     *
     * @return array<string, mixed>
     */
    public static function submit(object $subject, UserInterface $caller, \Closure $action): array
    {
        \assert($subject instanceof WerewolfGame);
        $seat = WerewolfCallers::seatOf($caller) ?? throw new ToolCallException('Only a seat can act in the game.');

        $game = $subject->game();
        $rejection = $game->submit($action($seat));
        if (null !== $rejection) {
            throw new ToolCallException($rejection->message);
        }

        return ['accepted' => true, 'phase' => $game->phase()->value, 'day' => $game->day(), 'message' => 'Recorded. Re-read the public-log resource and list your tools for the next step.'];
    }
}
