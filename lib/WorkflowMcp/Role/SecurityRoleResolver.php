<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Role;

use Symfony\Component\Security\Core\User\UserInterface;

/** Default resolver: the caller's Symfony security roles, independent of the subject. */
final readonly class SecurityRoleResolver implements RoleResolverInterface
{
    public function rolesFor(object $subject, UserInterface $caller): array
    {
        return array_values($caller->getRoles());
    }
}
