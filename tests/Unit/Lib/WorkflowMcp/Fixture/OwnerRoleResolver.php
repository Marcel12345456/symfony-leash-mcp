<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Leash\WorkflowMcp\Role\RoleResolverInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Custom resolver: the document's owner is ROLE_AUTHOR, everyone else ROLE_EDITOR, security roles ignored. */
final readonly class OwnerRoleResolver implements RoleResolverInterface
{
    public function rolesFor(object $subject, UserInterface $caller): array
    {
        return $subject instanceof Document && $subject->owner === $caller->getUserIdentifier() ? ['ROLE_AUTHOR'] : ['ROLE_EDITOR'];
    }
}
