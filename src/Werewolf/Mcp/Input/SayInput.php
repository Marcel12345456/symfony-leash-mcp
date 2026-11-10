<?php

declare(strict_types=1);

namespace App\Werewolf\Mcp\Input;

use App\Werewolf\Rules\Game;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class SayInput
{
    /** @param string $message Your public statement to the village */
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: Game::MAX_STATEMENT_LENGTH)]
        public string $message,
    ) {}
}
