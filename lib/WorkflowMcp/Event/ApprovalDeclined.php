<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Event;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A human declined a tool call with a reason (DecliningApprovalPolicyInterface). The transition was not applied;
 * listeners may record the reason on the subject, which is saved afterwards. Dispatched once per request state.
 */
final readonly class ApprovalDeclined
{
    public function __construct(
        public string $workflow,
        public object $subject,
        public UserInterface $caller,
        public string $tool,
        public string $reason,
    ) {}
}
