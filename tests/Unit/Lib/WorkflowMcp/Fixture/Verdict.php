<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

enum Verdict: string
{
    case Keep = 'keep';
    case Drop = 'drop';
}
