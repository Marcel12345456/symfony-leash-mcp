<?php

declare(strict_types=1);

namespace Leash\HumanInput\Policy;

/**
 * An approval policy whose form can say "no, because …". MCP's own `decline` carries no content, so a human who
 * wants to explain a refusal accepts the form with a no-answer and a reason; this policy recognises that answer.
 */
interface DecliningApprovalPolicyInterface extends ApprovalPolicyInterface
{
    /**
     * The human's reason when an accepted answer means "not approved", or null when it approves.
     *
     * @param array<string, mixed> $content
     */
    public function declineReason(array $content): ?string;
}
