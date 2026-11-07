<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Tool;

/** Tool arguments could not be turned into a valid input DTO. Each message names the field and the problem. */
final class InvalidInputException extends \RuntimeException
{
    /** @param non-empty-list<string> $violations */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(implode(' ', $violations));
    }
}
