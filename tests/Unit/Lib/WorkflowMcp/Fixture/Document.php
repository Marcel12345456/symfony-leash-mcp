<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

/** Subject of the synthetic, non-domain "document" workflow (G1). */
final class Document
{
    public bool $locked = false;

    public function __construct(
        private string $status = 'draft',
        public string $owner = 'alice',
    ) {}

    public function getStatus(): string
    {
        return $this->status;
    }

    /** @param array<string, mixed> $context */
    public function setStatus(string $status, array $context = []): void
    {
        $this->status = $status;
    }
}
