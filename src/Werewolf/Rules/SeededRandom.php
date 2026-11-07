<?php

declare(strict_types=1);

namespace App\Werewolf\Rules;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * Stateless seeded randomness: every draw is derived from the game seed plus a context string
 * ("roles", "night:2:victim"), so a game reloaded from storage draws the same values without
 * persisting RNG state.
 */
final readonly class SeededRandom
{
    public function __construct(private int $seed) {}

    /**
     * @template T
     *
     * @param non-empty-list<T> $items
     *
     * @return T
     */
    public function pick(string $context, array $items): mixed
    {
        return $items[$this->randomizer($context)->getInt(0, \count($items) - 1)];
    }

    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return list<T>
     */
    public function shuffle(string $context, array $items): array
    {
        return $this->randomizer($context)->shuffleArray($items);
    }

    private function randomizer(string $context): Randomizer
    {
        return new Randomizer(new Xoshiro256StarStar(hash('sha256', $this->seed . ':' . $context, true)));
    }
}
