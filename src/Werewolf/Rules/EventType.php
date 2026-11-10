<?php

declare(strict_types=1);

namespace App\Werewolf\Rules;

enum EventType: string
{
    case GameStarted = 'game_started';
    case PackFormed = 'pack_formed';
    case PhaseStarted = 'phase_started';
    case VictimChosen = 'victim_chosen';
    case SeatInspected = 'seat_inspected';
    case SeatKilled = 'seat_killed';
    case NobodyKilled = 'nobody_killed';
    case Statement = 'statement';
    case TurnSkipped = 'turn_skipped';
    case VoteCast = 'vote_cast';
    case SeatEliminated = 'seat_eliminated';
    case NobodyEliminated = 'nobody_eliminated';
    case GameOver = 'game_over';
}
