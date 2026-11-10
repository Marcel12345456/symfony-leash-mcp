<?php

declare(strict_types=1);

namespace App\Werewolf\Rules;

enum Winner: string
{
    case Wolves = 'wolves';
    case Village = 'village';
    case Draw = 'draw';
}
