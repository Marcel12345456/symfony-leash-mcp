<?php

declare(strict_types=1);

namespace App\Werewolf\Rules;

/** Values match the places of the `werewolf_game` workflow. */
enum Phase: string
{
    case Night = 'night';
    case Dawn = 'dawn';
    case DayDiscussion = 'day_discussion';
    case DayVote = 'day_vote';
    case Over = 'over';
}
