<?php

declare(strict_types=1);

namespace App\Werewolf\Mcp\Input;

use App\Werewolf\Rules\Game;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class VoteInput
{
    /**
     * @param int    $target Seat number of a living seat (see the public-log resource)
     * @param string $reason Why you vote for this seat, in one short public sentence the whole village sees
     */
    public function __construct(
        #[Assert\Range(min: 1, max: Game::SEATS)]
        public int $target,
        #[Assert\Length(max: Game::MAX_VOTE_REASON_LENGTH)]
        public string $reason = '',
    ) {}
}
