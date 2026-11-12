<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Client;

use Leash\HumanInput\Client\HumanInputToolCaller;
use Leash\HumanInput\Client\McpCallFailed;
use Leash\HumanInput\Client\ToolCall as HumanInputCall;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * Agent toolbox whose calls may need a human (PLAN §3.3): tools are listed by the inner toolbox, calls go through
 * HumanInputToolCaller, so an `input_required` becomes a pending input and the model is told to stop.
 */
final readonly class HumanInputToolbox implements ToolboxInterface
{
    public function __construct(
        private McpClientToolbox $tools,
        private HumanInputToolCaller $caller,
        private string $endpoint,
        private string $subjectRef,
        private string $callerId,
    ) {}

    public function getTools(): array
    {
        return $this->tools->getTools();
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        try {
            $result = $this->caller->call(new HumanInputCall($this->endpoint, $this->subjectRef, $this->callerId, $toolCall->getName(), $toolCall->getArguments()));
        } catch (McpCallFailed $e) {
            return new ToolResult($toolCall, \sprintf('Error: "%s" failed: %s', $toolCall->getName(), $e->getMessage()));
        }

        return new ToolResult($toolCall, McpClientToolbox::render($result));
    }
}
