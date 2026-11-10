<?php

declare(strict_types=1);

namespace App\Agent\Werewolf;

/** One runner step: the seat turns it played and whether the deadline path ran. */
final readonly class Step
{
    /** @param list<SeatTurn> $turns */
    public function __construct(
        public StepOutcome $outcome,
        public array $turns = [],
        public bool $expired = false,
        public bool $gameOver = false,
    ) {}
}
