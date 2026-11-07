<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Tool;

use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;

/** Model-facing tool results: every error says what went wrong and what the caller can do instead. */
final class ToolResults
{
    /**
     * @param list<string> $places
     * @param list<string> $available
     */
    public static function unavailable(string $tool, array $places, array $available): CallToolResult
    {
        return self::error(\sprintf(
            '"%s" is not available to your role in state "%s". %s',
            $tool,
            implode(', ', $places),
            self::availableNow($available),
        ));
    }

    /**
     * @param non-empty-list<string> $violations
     * @param list<string>           $available
     */
    public static function invalidInput(string $tool, array $violations, array $available): CallToolResult
    {
        return self::error(\sprintf('Invalid input for "%s": %s Fix the arguments and call "%s" again. %s', $tool, implode(' ', $violations), $tool, self::availableNow($available)));
    }

    /**
     * @param list<string> $places
     * @param list<string> $available
     */
    public static function transitionResult(string $message, array $places, array $available): CallToolResult
    {
        $state = implode(', ', $places);

        return new CallToolResult(
            [new TextContent(\sprintf('%s State is now "%s". %s', $message, $state, self::availableNow($available)))],
            false,
            ['state' => $state, 'availableTools' => $available, 'message' => $message],
        );
    }

    /**
     * Rebuilds a transition result from its structuredContent (replayed to duplicate retries).
     *
     * @param array<string, mixed> $stored
     */
    public static function fromStored(array $stored): CallToolResult
    {
        $state = $stored['state'] ?? null;
        $message = $stored['message'] ?? null;
        $available = $stored['availableTools'] ?? null;
        if (!\is_string($state) || !\is_string($message) || !\is_array($available) || !array_is_list($available)) {
            return self::error('The stored result of this request is unreadable; call the tool again without inputResponses.');
        }

        return self::transitionResult($message, explode(', ', $state), array_values(array_filter($available, \is_string(...))));
    }

    public static function error(string $message): CallToolResult
    {
        return CallToolResult::error([new TextContent($message)]);
    }

    /** @param list<string> $available */
    private static function availableNow(array $available): string
    {
        return [] === $available ? 'No tools are available to you now.' : 'Available now: ' . implode(', ', $available) . '.';
    }
}
