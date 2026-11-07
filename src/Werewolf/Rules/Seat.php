<?php

declare(strict_types=1);

namespace App\Werewolf\Rules;

final readonly class Seat
{
    public function __construct(
        public int $number,
        public string $name,
        public Role $role,
        public bool $alive = true,
    ) {}

    public function dead(): self
    {
        return new self($this->number, $this->name, $this->role, false);
    }

    public function label(): string
    {
        return \sprintf('%s (seat %d)', $this->name, $this->number);
    }
}
