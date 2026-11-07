<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Schema;

use Symfony\AI\Platform\Contract\JsonSchema\Factory;

/**
 * JSON schemas for MCP tools (decisions/013, 015): input schemas from DTO classes via the ai-platform factory,
 * plus the fixed output schema of transition tools `{state, availableTools, message}` (decisions/011).
 */
final readonly class SchemaFactory
{
    private const array STRUCTURAL_KEYWORDS = ['type' => true, 'enum' => true, 'items' => true, 'description' => true, 'properties' => true, 'required' => true, 'additionalProperties' => true];

    public function __construct(private Factory $factory = new Factory()) {}

    /**
     * @param class-string|null $inputClass
     *
     * @return array<string, mixed>
     */
    public function inputSchema(?string $inputClass): array
    {
        $schema = null === $inputClass ? null : $this->factory->buildProperties($inputClass);

        return null === $schema ? ['type' => 'object', 'properties' => new \stdClass()] : self::normalize($schema);
    }

    /** @return array<string, mixed> */
    public function transitionOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'state' => ['type' => 'string', 'description' => 'Current state of the subject after the call.'],
                'availableTools' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Tools you can call now.'],
                'message' => ['type' => 'string'],
            ],
            'required' => ['state', 'availableTools', 'message'],
            'additionalProperties' => false,
        ];
    }

    /**
     * WORKAROUND (decisions/013, BUGS.md): the platform factory requires nullable constructor arguments and omits
     * null from nullable enums. Its constraint keywords (incl. OpenAPI `nullable`, boolean `exclusiveMinimum`) are dropped.
     *
     * @param array<array-key, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private static function normalize(array $schema): array
    {
        $properties = \is_array($schema['properties'] ?? null) ? $schema['properties'] : [];
        $nullable = [];

        foreach ($properties as $name => $property) {
            if (!\is_array($property)) {
                continue;
            }
            // Structural keywords only (decisions/015): the SDK rejects schema violations with a JSON-RPC error
            // before the tool runs, so constraints are left to the Validator, whose messages are actionable.
            $property = array_intersect_key($property, self::STRUCTURAL_KEYWORDS);

            $type = $property['type'] ?? null;
            if (\is_array($type) && \in_array('null', $type, true)) {
                $nullable[] = (string) $name;
                if (\is_array($property['enum'] ?? null) && !\in_array(null, $property['enum'], true)) {
                    $property['enum'][] = null;
                }
            }

            $properties[$name] = $property;
        }

        $result = ['type' => 'object', 'properties' => [] === $properties ? new \stdClass() : $properties];
        $required = \is_array($schema['required'] ?? null)
            ? array_values(array_diff(array_filter($schema['required'], \is_string(...)), $nullable))
            : [];
        if ([] !== $required) {
            $result['required'] = $required;
        }
        $result['additionalProperties'] = false;

        return $result;
    }
}
