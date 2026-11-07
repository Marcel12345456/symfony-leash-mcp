<?php

declare(strict_types=1);

namespace App\RefundDesk\Entity;

enum CaseEventType: string
{
    case ToolCalled = 'tool_called';
    case TransitionApplied = 'transition_applied';
    case CapabilityDenied = 'capability_denied';
    case InputRequested = 'input_requested';
    case InputAnswered = 'input_answered';
    case AgentNote = 'agent_note';
}
