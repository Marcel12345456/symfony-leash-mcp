<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Capability;

use Leash\WorkflowMcp\Metadata\WorkflowMetadata;
use Leash\WorkflowMcp\PlaceTool\PlaceToolRegistry;
use Leash\WorkflowMcp\Role\RoleResolverInterface;
use Leash\WorkflowMcp\Role\SecurityRoleResolver;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Subject + caller → visible tools (PLAN §3.2 visibility rule, decisions/012):
 * enabled transitions (guards run) and tools of the current places, filtered by the caller's roles.
 */
final readonly class ScopedCapabilityProvider
{
    public function __construct(
        private PlaceToolRegistry $placeTools,
        #[AutowireLocator(RoleResolverInterface::TAG)]
        private ContainerInterface $roleResolvers,
        private SecurityRoleResolver $defaultRoleResolver = new SecurityRoleResolver(),
    ) {}

    /** @return list<string> */
    public function rolesFor(WorkflowInterface $workflow, object $subject, UserInterface $caller): array
    {
        $class = WorkflowMetadata::roleResolver($workflow);
        if (null === $class) {
            return $this->defaultRoleResolver->rolesFor($subject, $caller);
        }

        $resolver = $this->roleResolvers->get($class);
        if (!$resolver instanceof RoleResolverInterface) {
            throw new \LogicException(\sprintf('Workflow "%s": mcp_role_resolver "%s" must implement %s.', $workflow->getName(), $class, RoleResolverInterface::class));
        }

        return $resolver->rolesFor($subject, $caller);
    }

    /** @return list<VisibleTool> */
    public function visibleTools(WorkflowInterface $workflow, object $subject, UserInterface $caller): array
    {
        return $this->visibleToolsForRoles($workflow, $subject, $this->rolesFor($workflow, $subject, $caller));
    }

    /**
     * @param list<string> $roles
     *
     * @return list<VisibleTool>
     */
    public function visibleToolsForRoles(WorkflowInterface $workflow, object $subject, array $roles): array
    {
        $tools = [];

        foreach ($workflow->getEnabledTransitions($subject) as $transition) {
            if ([] === array_intersect(WorkflowMetadata::transitionRoles($workflow, $transition), $roles)) {
                continue;
            }
            $name = WorkflowMetadata::toolName($workflow, $transition);
            $tools[$name] ??= VisibleTool::forTransition(
                $name,
                WorkflowMetadata::transitionDescription($workflow, $transition),
                WorkflowMetadata::transitionInput($workflow, $transition),
                $transition,
            );
        }

        foreach (array_keys($workflow->getMarking($subject)->getPlaces()) as $place) {
            foreach (WorkflowMetadata::placeTools($workflow, (string) $place) as $name => $toolRoles) {
                if (isset($tools[$name]) || [] === array_intersect($toolRoles, $roles)) {
                    continue;
                }
                [$meta, $tool] = $this->placeTools->get($workflow->getName(), $name);
                $tools[$name] = VisibleTool::forPlace($name, $meta->description, $meta->readOnly, $meta->input, $tool);
            }
        }

        return array_values($tools);
    }
}
