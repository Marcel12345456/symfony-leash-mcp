<?php

declare(strict_types=1);

namespace App\Werewolf\Rules\Action;

final readonly class Say implements Action
{
    public function __construct(
        public int $seat,
        public string $message,
    ) {}

    public function actor(): int
    {
        return $this->seat;
    }
}
