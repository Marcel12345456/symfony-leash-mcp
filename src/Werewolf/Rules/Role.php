<?php

declare(strict_types=1);

namespace App\Werewolf\Rules;

enum Role: string
{
    case Wolf = 'wolf';
    case Seer = 'seer';
    case Villager = 'villager';

    /** 6 seats: 2 wolves, 1 seer, 3 villagers. */
    public const array COMPOSITION = [self::Wolf, self::Wolf, self::Seer, self::Villager, self::Villager, self::Villager];
}
