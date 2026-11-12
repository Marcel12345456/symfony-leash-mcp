<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\PlaceTool;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Optional for a place tool: the values a human may pick for some input fields right now (e.g. living seats).
 * ElicitMissingInputPolicy offers them as a choice when it asks for a missing field.
 */
interface InputChoicesInterface
{
    /** @return array<string, array<int|string, string>> field => (value => label) */
    public function inputChoices(object $subject, UserInterface $caller): array;
}
