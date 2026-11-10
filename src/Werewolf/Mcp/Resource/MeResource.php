<?php

declare(strict_types=1);

namespace App\Werewolf\Mcp\Resource;

use App\Werewolf\Entity\WerewolfGame;
use App\Werewolf\Rules\Audience;
use App\Werewolf\Rules\Event;
use App\Werewolf\Security\WerewolfCallers;
use Leash\WorkflowMcp\PlaceResource\AsPlaceResource;
use Leash\WorkflowMcp\PlaceResource\PlaceResourceInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsPlaceResource(workflow: 'werewolf_game', name: 'me', uri: 'werewolf://game/{subjectId}/me', description: 'Your seat: role, whether the game waits for you, and your private knowledge.')]
final readonly class MeResource implements PlaceResourceInterface
{
    public function __invoke(object $subject, UserInterface $caller): array
    {
        \assert($subject instanceof WerewolfGame);
        $game = $subject->game();
        $number = WerewolfCallers::seatOf($caller) ?? 0;
        $seat = $game->seat($number);

        return [
            'seat' => $seat->number,
            'name' => $seat->name,
            'role' => $seat->role->value,
            'alive' => $seat->alive,
            'yourTurn' => \in_array($number, $game->pendingSeats(), true),
            'knowledge' => PublicLogResource::texts(array_filter($game->eventsVisibleTo($number), static fn(Event $e): bool => Audience::Public !== $e->audience)),
        ];
    }
}
