<?php

declare(strict_types=1);

namespace App\Werewolf\Rules\Action;

final readonly class Vote implements Action
{
    public function __construct(
        public int $seat,
        public int $target,
    ) {}

    public function actor(): int
    {
        return $this->seat;
    }
}
