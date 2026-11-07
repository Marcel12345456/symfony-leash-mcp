<?php

declare(strict_types=1);

namespace App\Werewolf\Rules;

/** Who may read an event: everyone, the wolves, or the seer. */
enum Audience: string
{
    case Public = 'public';
    case Wolves = 'wolves';
    case Seer = 'seer';
}
