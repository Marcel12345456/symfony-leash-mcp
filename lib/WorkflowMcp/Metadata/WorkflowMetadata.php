<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Metadata;

use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Typed reader for the MCP metadata contract (PLAN §3.2, decisions/012).
 *
 * Workflow: `mcp_prefix` (default: workflow name), `mcp_subject` (entity class), `mcp_role_resolver` (resolver class).
 * Place: `mcp_tools` (tool name => list of roles), `mcp_resources` (resource name => list of roles).
 * Transition: `mcp_roles`, `mcp_description`, `mcp_input` (DTO class).
 */
final readonly class WorkflowMetadata
{
    public static function prefix(WorkflowInterface $workflow): string
    {
        return self::optionalString($workflow, $workflow->getMetadataStore()->getWorkflowMetadata()['mcp_prefix'] ?? null, 'mcp_prefix')
            ?? $workflow->getName();
    }

    public static function subjectClass(WorkflowInterface $workflow): ?string
    {
        return self::optionalString($workflow, $workflow->getMetadataStore()->getWorkflowMetadata()['mcp_subject'] ?? null, 'mcp_subject');
    }

    public static function roleResolver(WorkflowInterface $workflow): ?string
    {
        return self::optionalString($workflow, $workflow->getMetadataStore()->getWorkflowMetadata()['mcp_role_resolver'] ?? null, 'mcp_role_resolver');
    }

    public static function toolName(WorkflowInterface $workflow, Transition $transition): string
    {
        return self::prefix($workflow) . '_' . $transition->getName();
    }

    /** @return list<string> */
    public static function transitionRoles(WorkflowInterface $workflow, Transition $transition): array
    {
        return self::roleList($workflow, $workflow->getMetadataStore()->getTransitionMetadata($transition)['mcp_roles'] ?? [], 'mcp_roles of ' . $transition->getName());
    }

    public static function transitionDescription(WorkflowInterface $workflow, Transition $transition): string
    {
        return self::optionalString($workflow, $workflow->getMetadataStore()->getTransitionMetadata($transition)['mcp_description'] ?? null, 'mcp_description of ' . $transition->getName())
            ?? \sprintf('Apply the "%s" transition.', $transition->getName());
    }

    /** @return class-string|null */
    public static function transitionInput(WorkflowInterface $workflow, Transition $transition): ?string
    {
        $class = self::optionalString($workflow, $workflow->getMetadataStore()->getTransitionMetadata($transition)['mcp_input'] ?? null, 'mcp_input of ' . $transition->getName());
        if (null !== $class && !class_exists($class)) {
            throw new \LogicException(\sprintf('Workflow "%s": mcp_input class "%s" of transition "%s" does not exist.', $workflow->getName(), $class, $transition->getName()));
        }

        return $class;
    }

    /** Name of the approval policy (HumanInput) that guards this transition, if any. */
    public static function transitionApproval(WorkflowInterface $workflow, Transition $transition): ?string
    {
        return self::optionalString($workflow, $workflow->getMetadataStore()->getTransitionMetadata($transition)['mcp_approval'] ?? null, 'mcp_approval of ' . $transition->getName());
    }

    /** @return array<string, list<string>> tool name => roles */
    public static function placeTools(WorkflowInterface $workflow, string $place): array
    {
        return self::placeRoleMap($workflow, $place, 'mcp_tools');
    }

    /** @return array<string, list<string>> resource name => roles */
    public static function placeResources(WorkflowInterface $workflow, string $place): array
    {
        return self::placeRoleMap($workflow, $place, 'mcp_resources');
    }

    /** @return list<string> every role named anywhere in the workflow's MCP metadata */
    public static function allRoles(WorkflowInterface $workflow): array
    {
        $roles = [];
        foreach ($workflow->getDefinition()->getTransitions() as $transition) {
            $roles = [...$roles, ...self::transitionRoles($workflow, $transition)];
        }
        foreach ($workflow->getDefinition()->getPlaces() as $place) {
            foreach ([...array_values(self::placeTools($workflow, $place)), ...array_values(self::placeResources($workflow, $place))] as $placeRoles) {
                $roles = [...$roles, ...$placeRoles];
            }
        }

        return array_values(array_unique($roles));
    }

    /** @return array<string, list<string>> name => roles */
    private static function placeRoleMap(WorkflowInterface $workflow, string $place, string $key): array
    {
        $entries = $workflow->getMetadataStore()->getPlaceMetadata($place)[$key] ?? [];
        if (!\is_array($entries)) {
            throw new \LogicException(\sprintf('Workflow "%s": %s of place "%s" must be a map of name => roles.', $workflow->getName(), $key, $place));
        }

        $map = [];
        foreach ($entries as $name => $roles) {
            if (!\is_string($name)) {
                throw new \LogicException(\sprintf('Workflow "%s": %s of place "%s" must be keyed by name.', $workflow->getName(), $key, $place));
            }
            $map[$name] = self::roleList($workflow, $roles, \sprintf('%s.%s of place %s', $key, $name, $place));
        }

        return $map;
    }

    private static function optionalString(WorkflowInterface $workflow, mixed $value, string $key): ?string
    {
        if (null === $value || \is_string($value)) {
            return $value;
        }

        throw new \LogicException(\sprintf('Workflow "%s": %s must be a string.', $workflow->getName(), $key));
    }

    /** @return list<string> */
    private static function roleList(WorkflowInterface $workflow, mixed $value, string $key): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw new \LogicException(\sprintf('Workflow "%s": %s must be a list of roles.', $workflow->getName(), $key));
        }

        return array_map(static fn(mixed $role): string => \is_string($role) ? $role
            : throw new \LogicException(\sprintf('Workflow "%s": %s must contain only strings.', $workflow->getName(), $key)), $value);
    }
}
