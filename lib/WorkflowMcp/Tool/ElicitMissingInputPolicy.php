<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Tool;

use Mcp\Schema\Elicitation\ElicitationSchema;
use Mcp\Schema\Request\ElicitRequest;

/**
 * The elicit-missing step (PLAN §3.2, decisions/035): a human-backed caller may call a tool without its required
 * fields; it is asked for exactly the missing ones, and the answer is merged into the arguments before the DTO is
 * built. Everyone else keeps the schema's `required` list and its JSON-RPC error. A caller is human-backed when its
 * workflow roles contain `$role`.
 */
final readonly class ElicitMissingInputPolicy
{
    public const string HUMAN_BACKED = 'ROLE_HUMAN_BACKED';
    public const string KEY = 'input';

    public function __construct(private string $role = self::HUMAN_BACKED) {}

    /** @param list<string> $roles the caller's workflow roles */
    public function isHumanBacked(array $roles): bool
    {
        return \in_array($this->role, $roles, true);
    }

    /**
     * The schema a human-backed caller sees: no `required` list, so `{}` reaches the tool.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public function relax(array $schema): array
    {
        unset($schema['required']);

        return $schema;
    }

    /**
     * @param array<string, mixed> $schema    the tool's input schema
     * @param array<string, mixed> $arguments
     *
     * @return list<string> required fields not in the arguments, in schema order
     */
    public function missingFields(array $schema, array $arguments): array
    {
        $required = \is_array($schema['required'] ?? null) ? $schema['required'] : [];

        return array_values(array_filter($required, static fn(mixed $f): bool => \is_string($f) && !\array_key_exists($f, $arguments)));
    }

    /**
     * A form for exactly the missing fields, typed from the tool's schema; fields with choices become a pick list.
     *
     * @param array<string, mixed>                     $schema
     * @param non-empty-list<string>                   $missing
     * @param array<string, array<int|string, string>> $choices field => (value => label)
     */
    public function inputRequest(string $tool, array $schema, array $missing, array $choices = []): ElicitRequest
    {
        $properties = [];
        foreach ($missing as $field) {
            $source = self::property($schema, $field);
            $property = ['title' => $field] + (\is_string($source['description'] ?? null) ? ['description' => $source['description']] : []);
            if ([] !== ($choices[$field] ?? [])) {
                $options = [];
                foreach ($choices[$field] as $value => $label) {
                    $options[] = ['const' => (string) $value, 'title' => $label];
                }
                $property += ['type' => 'string', 'oneOf' => $options];
            } else {
                $type = $source['type'] ?? null;
                $type = \is_array($type) ? array_values(array_filter($type, static fn(mixed $t): bool => 'null' !== $t))[0] ?? null : $type;
                $property += ['type' => \in_array($type, ['integer', 'number', 'boolean'], true) ? $type : 'string'];
                if (\is_array($source['enum'] ?? null)) {
                    $property['enum'] = array_values(array_map(strval(...), array_filter($source['enum'], \is_scalar(...))));
                }
            }
            $properties[$field] = $property;
        }

        return ElicitRequest::forForm(
            \sprintf('"%s" needs: %s.', $tool, implode(', ', $missing)),
            ElicitationSchema::fromArray(['type' => 'object', 'properties' => $properties, 'required' => $missing]),
        );
    }

    /**
     * The arguments with the answer merged in, each value converted to the type the tool's schema expects
     * (pick lists answer with strings). Fields the tool does not know are dropped.
     *
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $content
     *
     * @return array<string, mixed>
     */
    public function merge(array $schema, array $arguments, array $content): array
    {
        foreach ($content as $field => $value) {
            $property = self::property($schema, $field);
            if ([] === $property) {
                continue;
            }
            $type = $property['type'] ?? null;
            $arguments[$field] = match (true) {
                ('integer' === $type || (\is_array($type) && \in_array('integer', $type, true))) && \is_string($value) && is_numeric($value) => (int) $value,
                ('number' === $type || (\is_array($type) && \in_array('number', $type, true))) && \is_string($value) && is_numeric($value) => (float) $value,
                default => $value,
            };
        }

        return $arguments;
    }

    /**
     * @param array<string, mixed> $schema
     *
     * @return array<mixed>
     */
    private static function property(array $schema, string $field): array
    {
        $property = \is_array($schema['properties'] ?? null) ? ($schema['properties'][$field] ?? null) : null;

        return \is_array($property) ? $property : [];
    }
}
