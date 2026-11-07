<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Role;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Decides which workflow roles a caller holds for one subject. Matched against `mcp_roles` / `mcp_tools`.
 * A workflow picks its resolver with the workflow metadata `mcp_role_resolver: <class>`; default is SecurityRoleResolver.
 */
#[AutoconfigureTag(self::TAG)]
interface RoleResolverInterface
{
    public const string TAG = 'leash.workflow_mcp.role_resolver';

    /** @return list<string> */
    public function rolesFor(object $subject, UserInterface $caller): array;
}
