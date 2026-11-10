<?php

declare(strict_types=1);

namespace App\Agent;

/** How one agent run ended (decisions/032). Every outcome but Done ends the turn without a final answer. */
enum TurnOutcome: string
{
    case Done = 'done';
    case TimedOut = 'timed_out';
    case BudgetSpent = 'budget_spent';
    case Failed = 'failed';
}
