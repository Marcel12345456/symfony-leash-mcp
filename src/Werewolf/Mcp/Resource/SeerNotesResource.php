<?php

declare(strict_types=1);

namespace App\Werewolf\Mcp\Resource;

use App\Werewolf\Entity\WerewolfGame;
use App\Werewolf\Rules\Audience;
use App\Werewolf\Rules\Event;
use App\Werewolf\Rules\Role;
use Leash\WorkflowMcp\PlaceResource\AsPlaceResource;
use Leash\WorkflowMcp\PlaceResource\PlaceResourceInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsPlaceResource(workflow: 'werewolf_game', name: 'seer-notes', uri: 'werewolf://game/{subjectId}/seer-notes', description: 'Seer only: the result of every inspection so far.')]
final readonly class SeerNotesResource implements PlaceResourceInterface
{
    public function __invoke(object $subject, UserInterface $caller): array
    {
        \assert($subject instanceof WerewolfGame);
        $game = $subject->game();
        $inspections = array_filter($game->events(), static fn(Event $e): bool => Audience::Seer === $e->audience);

        return [
            'inspections' => array_values(array_map(static fn(Event $e): array => [
                'day' => $e->day,
                'seat' => $e->target,
                'wolf' => null !== $e->target && Role::Wolf === $game->seat($e->target)->role,
                'text' => $e->text,
            ], $inspections)),
        ];
    }
}
