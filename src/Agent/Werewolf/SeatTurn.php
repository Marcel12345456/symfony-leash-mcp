<?php

declare(strict_types=1);

namespace App\Agent\Werewolf;

use App\Agent\TurnOutcome;
use Symfony\AI\Platform\Message\MessageBag;

/** One seat's turn: the whole conversation (prompt, tool calls and results), its final text and how it ended. */
final readonly class SeatTurn
{
    public function __construct(
        public int $seat,
        public MessageBag $messages,
        public ?string $finalText,
        public int $toolCalls,
        public TurnOutcome $outcome,
        public ?string $error = null,
    ) {}
}
