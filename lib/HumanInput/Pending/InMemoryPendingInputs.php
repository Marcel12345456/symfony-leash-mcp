<?php

declare(strict_types=1);

namespace Leash\HumanInput\Pending;

/** Process-local store for tests and in-process demos. */
final class InMemoryPendingInputs implements PendingInputs
{
    /** @var array<string, PendingInput> */
    private array $items = [];

    public function get(string $id): ?PendingInput
    {
        return $this->items[$id] ?? null;
    }

    public function save(PendingInput $pending): void
    {
        $this->items[$pending->id] = $pending;
    }

    public function withStatus(PendingInputStatus $status): array
    {
        return array_values(array_filter($this->items, static fn(PendingInput $p): bool => $status === $p->status));
    }
}
