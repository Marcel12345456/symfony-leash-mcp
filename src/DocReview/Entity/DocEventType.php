<?php

declare(strict_types=1);

namespace App\DocReview\Entity;

enum DocEventType: string
{
    case ToolCalled = 'tool_called';
    case TransitionApplied = 'transition_applied';
    case CapabilityDenied = 'capability_denied';
    case InputDeclined = 'input_declined';
}
