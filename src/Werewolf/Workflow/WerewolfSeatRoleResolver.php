<?php

declare(strict_types=1);

namespace App\Werewolf\Workflow;

use App\Werewolf\Entity\WerewolfGame;
use App\Werewolf\Rules\Phase;
use App\Werewolf\Rules\Role;
use App\Werewolf\Security\WerewolfCallers;
use Leash\WorkflowMcp\Role\RoleResolverInterface;
use Leash\WorkflowMcp\Tool\ElicitMissingInputPolicy;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Seat → workflow roles for one game (PLAN §3.5). Roles stay any-of, so turn-bound tools use dedicated roles:
 * a dead seat gets only ROLE_DEAD; a living seat gets ROLE_ALIVE, its identity role and, while the phase waits for it,
 * ROLE_ON_TURN (plus ROLE_WOLF_ON_TURN / ROLE_SEER_ON_TURN at night). A seat that acted loses its tool.
 * The game's human seat is also human-backed: it is asked for missing tool input (decisions/035).
 */
final readonly class WerewolfSeatRoleResolver implements RoleResolverInterface
{
    public const string MODERATOR = 'ROLE_MODERATOR';
    public const string ALIVE = 'ROLE_ALIVE';
    public const string DEAD = 'ROLE_DEAD';
    public const string ON_TURN = 'ROLE_ON_TURN';
    public const string WOLF_ON_TURN = 'ROLE_WOLF_ON_TURN';
    public const string SEER_ON_TURN = 'ROLE_SEER_ON_TURN';

    public function rolesFor(object $subject, UserInterface $caller): array
    {
        if (!$subject instanceof WerewolfGame) {
            return [];
        }
        if (WerewolfCallers::MODERATOR === $caller->getUserIdentifier()) {
            return [self::MODERATOR];
        }

        $game = $subject->game();
        $number = WerewolfCallers::seatOf($caller);
        $seat = null === $number ? null : ($game->seats()[$number] ?? null);
        if (null === $seat) {
            return [];
        }
        if (!$seat->alive) {
            return [self::DEAD];
        }

        $roles = [self::ALIVE, 'ROLE_' . strtoupper($seat->role->value)];
        if ($seat->number === $subject->humanSeat()) {
            $roles[] = ElicitMissingInputPolicy::HUMAN_BACKED;
        }
        if (\in_array($seat->number, $game->pendingSeats(), true)) {
            $roles[] = self::ON_TURN;
            if (Phase::Night === $game->phase()) {
                $roles[] = Role::Wolf === $seat->role ? self::WOLF_ON_TURN : self::SEER_ON_TURN;
            }
        }

        return $roles;
    }
}
