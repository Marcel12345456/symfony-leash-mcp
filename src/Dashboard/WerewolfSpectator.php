<?php

declare(strict_types=1);

namespace App\Dashboard;

use App\Werewolf\Entity\WerewolfGame;
use App\Werewolf\Rules\Audience;
use App\Werewolf\Rules\Event;
use App\Werewolf\Rules\Seat;
use App\Werewolf\Security\WerewolfCallers;
use Doctrine\ORM\EntityManagerInterface;
use Leash\WorkflowMcp\Capability\ScopedCapabilityProvider;
use Leash\WorkflowMcp\Capability\VisibleResource;
use Leash\WorkflowMcp\Capability\VisibleTool;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Spectator view of one Werewolf game (PLAN §3.5, §3.7): the public log, the denials, and per seat what the MCP
 * server shows that seat right now. The panel asks the same ScopedCapabilityProvider the endpoint uses, so it cannot
 * drift from `tools/list` / `resources/list` (pinned by SeatPanelMatchesServerTest).
 * Without `reveal`, roles, private events and resource contents (`me` names the role) stay hidden.
 */
final readonly class WerewolfSpectator
{
    /** Its content is the public event list, which the view shows anyway. */
    private const string PUBLIC_LOG = 'public-log';

    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Target('werewolf_game')]
        private WorkflowInterface $workflow,
        private ScopedCapabilityProvider $capabilities,
    ) {}

    /** @return list<string> game ids, newest first by id */
    public function gameIds(): array
    {
        $ids = array_map(static fn(WerewolfGame $g): string => $g->getId(), $this->entityManager->getRepository(WerewolfGame::class)->findAll());
        rsort($ids);

        return $ids;
    }

    /**
     * @return array{id: string, day: int, phase: string, winner: ?string, reveal: bool, humanSeat: ?int,
     *     seats: list<array{seat: int, name: string, alive: bool, role: ?string, roles: list<string>, tools: list<array{name: string, readOnly: bool}>, resources: list<array{name: string, uri: string, content: array<array-key, mixed>|string|null}>}>,
     *     events: list<array{day: int, phase: string, type: string, audience: string, actor: ?int, text: string}>,
     *     denials: list<array{caller: string, capability: string, places: list<string>, day: int}>}|null
     */
    public function view(string $gameId, bool $reveal): ?array
    {
        $this->entityManager->clear();
        $entity = $this->entityManager->find(WerewolfGame::class, $gameId);
        if (null === $entity) {
            return null;
        }
        $game = $entity->game();
        $events = array_filter($game->events(), static fn(Event $e): bool => $reveal || Audience::Public === $e->audience);

        return [
            'id' => $gameId,
            'day' => $game->day(),
            'phase' => $game->phase()->value,
            'winner' => $game->winner()?->value,
            'reveal' => $reveal,
            'humanSeat' => $entity->humanSeat(),
            'seats' => array_values(array_map(fn(Seat $s): array => [
                'seat' => $s->number,
                'name' => $s->name,
                'alive' => $s->alive,
                'role' => $reveal ? $s->role->value : null,
            ] + $this->panel($entity, $s->number, $reveal), $game->seats())),
            'events' => array_values(array_map(static fn(Event $e): array => [
                'day' => $e->day, 'phase' => $e->phase->value, 'type' => $e->type->value,
                'audience' => $e->audience->value, 'actor' => $e->actor, 'text' => $e->text,
            ], $events)),
            'denials' => $entity->denials(),
        ];
    }

    /**
     * What this seat sees over MCP right now.
     *
     * @return array{roles: list<string>, tools: list<array{name: string, readOnly: bool}>, resources: list<array{name: string, uri: string, content: array<array-key, mixed>|string|null}>}
     */
    public function panel(WerewolfGame $game, int $seat, bool $reveal): array
    {
        $caller = new InMemoryUser(WerewolfCallers::seat($seat), null);
        $resources = $this->capabilities->visibleResources($this->workflow, $game, $caller);

        return [
            'roles' => $reveal ? $this->capabilities->rolesFor($this->workflow, $game, $caller) : [],
            'tools' => array_map(static fn(VisibleTool $t): array => ['name' => $t->name, 'readOnly' => $t->readOnly], $this->capabilities->visibleTools($this->workflow, $game, $caller)),
            'resources' => array_map(static fn(VisibleResource $r): array => [
                'name' => $r->name,
                'uri' => str_replace('{subjectId}', $game->getId(), $r->uri),
                'content' => $reveal && self::PUBLIC_LOG !== $r->name ? ($r->resource)($game, $caller) : null,
            ], $resources),
        ];
    }
}
