<?php

declare(strict_types=1);

namespace App\Werewolf\Rules;

enum RejectionReason: string
{
    case GameOver = 'game_over';
    case UnknownSeat = 'unknown_seat';
    case ActorDead = 'actor_dead';
    case WrongPhase = 'wrong_phase';
    case NotYourRole = 'not_your_role';
    case NotYourTurn = 'not_your_turn';
    case AlreadyActed = 'already_acted';
    case TargetDead = 'target_dead';
    case InvalidTarget = 'invalid_target';
    case InvalidMessage = 'invalid_message';
}
