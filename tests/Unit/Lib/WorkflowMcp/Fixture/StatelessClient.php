<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Mcp\Server\Builder;

/** Drives a built server in-process on the stateless 2026-07-28 wire (decisions/014): no HTTP, no session. */
final class StatelessClient
{
    private int $id = 0;

    public function __construct(private readonly Builder $builder) {}

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed> the JSON-RPC response
     */
    public function request(string $method, array $params = []): array
    {
        $params['_meta'] = [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => new \stdClass(),
        ];
        $json = json_encode(['jsonrpc' => '2.0', 'id' => ++$this->id, 'method' => $method, 'params' => $params], \JSON_THROW_ON_ERROR);

        return self::object(json_decode($this->builder->buildStateless()->handleInline($json)->toJson(), true, flags: \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed> the tools/call result
     */
    public function call(string $tool, array $arguments = []): array
    {
        $response = $this->request('tools/call', ['name' => $tool, 'arguments' => (object) $arguments]);
        \assert(\is_array($response['result'] ?? null), 'Expected a result, got ' . json_encode($response));

        return self::object($response['result']);
    }

    /** @return list<array<string, mixed>> */
    public function listTools(): array
    {
        $tools = self::at($this->request('tools/list'), 'result', 'tools');
        \assert(\is_array($tools), 'Expected a tool list.');

        return array_values(array_map(self::object(...), $tools));
    }

    /** Walks decoded JSON by keys; null when a step is missing. */
    public static function at(mixed $data, int|string ...$path): mixed
    {
        foreach ($path as $key) {
            if (!\is_array($data) || !\array_key_exists($key, $data)) {
                return null;
            }
            $data = $data[$key];
        }

        return $data;
    }

    /** @param array<string, mixed> $result a tools/call result */
    public static function text(array $result): string
    {
        $text = self::at($result, 'content', 0, 'text');

        return \is_string($text) ? $text : '';
    }

    /** @return array<string, mixed> */
    private static function object(mixed $data): array
    {
        \assert(\is_array($data));
        $out = [];
        foreach ($data as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }
}
