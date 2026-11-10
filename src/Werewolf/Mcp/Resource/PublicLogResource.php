<?php

declare(strict_types=1);

namespace App\Werewolf\Mcp\Resource;

use App\Werewolf\Entity\WerewolfGame;
use App\Werewolf\Rules\Audience;
use App\Werewolf\Rules\Event;
use App\Werewolf\Rules\Seat;
use Leash\WorkflowMcp\PlaceResource\AsPlaceResource;
use Leash\WorkflowMcp\PlaceResource\PlaceResourceInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsPlaceResource(workflow: 'werewolf_game', name: 'public-log', uri: 'werewolf://game/{subjectId}/public-log', description: 'Everything the whole village saw: phase, living seats, deaths, statements, votes.')]
final readonly class PublicLogResource implements PlaceResourceInterface
{
    public function __invoke(object $subject, UserInterface $caller): array
    {
        \assert($subject instanceof WerewolfGame);
        $game = $subject->game();

        return [
            'day' => $game->day(),
            'phase' => $game->phase()->value,
            'winner' => $game->winner()?->value,
            'seats' => array_values(array_map(static fn(Seat $s): array => ['seat' => $s->number, 'name' => $s->name, 'alive' => $s->alive], $game->seats())),
            'events' => self::texts(array_filter($game->events(), static fn(Event $e): bool => Audience::Public === $e->audience)),
        ];
    }

    /**
     * @param array<Event> $events
     *
     * @return list<array{day: int, phase: string, text: string}>
     */
    public static function texts(array $events): array
    {
        return array_values(array_map(static fn(Event $e): array => ['day' => $e->day, 'phase' => $e->phase->value, 'text' => $e->text], $events));
    }
}
