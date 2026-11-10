<?php

declare(strict_types=1);

namespace App\Werewolf\Mcp\Input;

use App\Werewolf\Rules\Game;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class TargetInput
{
    /** @param int $target Seat number of a living seat (see the public-log resource) */
    public function __construct(
        #[Assert\Range(min: 1, max: Game::SEATS)]
        public int $target,
    ) {}
}
