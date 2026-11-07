<?php

declare(strict_types=1);

namespace Leash\HumanInput\Pending;

interface PendingInputs
{
    public function get(string $id): ?PendingInput;

    /** Inserts or updates. */
    public function save(PendingInput $pending): void;

    /** @return list<PendingInput> oldest first */
    public function withStatus(PendingInputStatus $status): array;
}
