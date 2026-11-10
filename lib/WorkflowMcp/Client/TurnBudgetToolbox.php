<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Client;

use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Caps the tool calls of one agent run (decisions/029). Calls past the budget never reach the inner toolbox; the
 * model is told to stop. The agent's own `maxToolCalls` counts rounds, not calls, and throws.
 */
final class TurnBudgetToolbox implements ToolboxInterface
{
    private int $calls = 0;

    public function __construct(
        private readonly ToolboxInterface $inner,
        private readonly int $budget,
    ) {}

    public function getTools(): array
    {
        return $this->inner->getTools();
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        if ($this->calls >= $this->budget) {
            return new ToolResult($toolCall, \sprintf('Error: the tool budget for this turn is spent (%d calls). Do not call more tools; end your turn.', $this->budget));
        }
        ++$this->calls;

        return $this->inner->execute($toolCall);
    }

    /** Calls that reached the inner toolbox. */
    public function calls(): int
    {
        return $this->calls;
    }
}
