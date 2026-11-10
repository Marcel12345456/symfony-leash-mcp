<?php

declare(strict_types=1);

namespace App\Werewolf\Mcp\Resource;

use App\Werewolf\Entity\WerewolfGame;
use App\Werewolf\Rules\Audience;
use App\Werewolf\Rules\Event;
use App\Werewolf\Rules\Role;
use App\Werewolf\Rules\Seat;
use Leash\WorkflowMcp\PlaceResource\AsPlaceResource;
use Leash\WorkflowMcp\PlaceResource\PlaceResourceInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsPlaceResource(workflow: 'werewolf_game', name: 'wolf-den', uri: 'werewolf://game/{subjectId}/wolf-den', description: 'Wolves only: your pack and the victims each wolf chose.')]
final readonly class WolfDenResource implements PlaceResourceInterface
{
    public function __invoke(object $subject, UserInterface $caller): array
    {
        \assert($subject instanceof WerewolfGame);
        $game = $subject->game();

        return [
            'pack' => array_values(array_map(static fn(Seat $s): int => $s->number, array_filter($game->seats(), static fn(Seat $s): bool => Role::Wolf === $s->role))),
            'events' => PublicLogResource::texts(array_filter($game->events(), static fn(Event $e): bool => Audience::Wolves === $e->audience)),
        ];
    }
}
