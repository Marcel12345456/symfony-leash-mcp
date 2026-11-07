<?php

declare(strict_types=1);

namespace Leash\HumanInput\Policy;

use Mcp\Schema\Request\ElicitRequest;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Decides whether a call needs a human, what to ask, and how an accepted answer changes the input.
 * Name a policy with `#[AsTaggedItem('<name>')]`; workflows refer to it by that name.
 */
#[AutoconfigureTag]
interface ApprovalPolicyInterface
{
    /** The form to show the human, or null when this call needs no input. Must not change anything. */
    public function inputRequest(object $subject, object $input): ?ElicitRequest;

    /**
     * Folds an accepted answer into the input that will be applied.
     *
     * @param array<string, mixed> $content
     */
    public function applyAnswer(object $input, array $content): object;
}
