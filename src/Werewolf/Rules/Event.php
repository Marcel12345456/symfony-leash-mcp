<?php

declare(strict_types=1);

namespace App\Werewolf\Rules;

final readonly class Event
{
    public function __construct(
        public int $day,
        public Phase $phase,
        public EventType $type,
        public Audience $audience,
        public string $text,
        public ?int $actor = null,
        public ?int $target = null,
    ) {}
}
