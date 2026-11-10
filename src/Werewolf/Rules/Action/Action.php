<?php

declare(strict_types=1);

namespace App\Werewolf\Rules\Action;

/** A seat's move; the engine validates it against the current phase, the seat's role and the board. */
interface Action
{
    public function actor(): int;
}
