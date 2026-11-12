<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Client;

use Mcp\Client;
use Mcp\Exception\ConnectionException;
use Mcp\Exception\RequestCancelledException;
use Mcp\Exception\RequestException;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Result\CallToolResult;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * Agent toolbox over one connected MCP client, for one agent run (decisions/027, 031).
 *
 * Workaround for symfony/ai-mcp-tool 0.15 `McpToolbox` (BUGS.md): tool names stay bare, every call reaches the
 * server (so a hidden tool is answered and logged there, not rejected locally), and server errors come back to the
 * model as text instead of a generic exception message.
 */
final class McpClientToolbox implements ToolboxInterface
{
    /** @var list<Tool>|null listed once per run */
    private ?array $tools = null;

    public function __construct(
        private readonly Client $client,
        private readonly ?float $timeoutSeconds = null,
    ) {}

    public function getTools(): array
    {
        if (null !== $this->tools) {
            return $this->tools;
        }

        $tools = [];
        $cursor = null;
        do {
            $page = $this->client->listTools($cursor);
            foreach ($page->tools as $tool) {
                // The schema is the server's; the platform types it as its own JSON Schema subset, which we cannot prove here.
                // @phpstan-ignore argument.type
                $tools[] = new Tool(new ExecutionReference(self::class, 'execute'), $tool->name, $tool->description ?? '', self::parameters($tool->inputSchema));
            }
            $cursor = $page->nextCursor;
        } while (null !== $cursor);

        return $this->tools = $tools;
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        try {
            $result = $this->client->callTool($toolCall->getName(), $toolCall->getArguments(), timeoutSeconds: $this->timeoutSeconds);
        } catch (RequestException|ConnectionException|RequestCancelledException $e) {
            return new ToolResult($toolCall, \sprintf('Error: "%s" failed (%d): %s', $toolCall->getName(), $e->getCode(), $e->getMessage()));
        }

        return new ToolResult($toolCall, self::render($result));
    }

    /** The text a model gets for a tool result: the structured content as JSON, or the text; errors prefixed. */
    public static function render(CallToolResult $result): string
    {
        $text = implode("\n", array_map(
            static fn(TextContent $c): string => \is_string($c->text) ? $c->text : json_encode($c->text, \JSON_THROW_ON_ERROR),
            array_filter($result->content, static fn(object $c): bool => $c instanceof TextContent),
        ));
        if ($result->isError) {
            return 'Error: ' . $text;
        }

        return null !== $result->structuredContent ? json_encode($result->structuredContent, \JSON_THROW_ON_ERROR) : $text;
    }

    /**
     * Platforms reject an object schema without properties; no parameters means no schema. The SDK keeps empty
     * `properties` as an empty object.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>|null
     */
    private static function parameters(array $schema): ?array
    {
        $properties = $schema['properties'] ?? [];
        $empty = [] === $properties || ($properties instanceof \stdClass && [] === get_object_vars($properties));

        return $empty ? null : $schema;
    }
}
