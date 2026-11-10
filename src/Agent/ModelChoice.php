<?php

declare(strict_types=1);

namespace App\Agent;

use Symfony\AI\Platform\PlatformInterface;

/** Which platform and model play a run, and whether the model thinks (decisions/029). */
final readonly class ModelChoice
{
    /** @param non-empty-string $model */
    public function __construct(
        public PlatformInterface $platform,
        public string $model,
        public bool $think = true,
    ) {}

    /** @return array<string, mixed> agent call options */
    public function options(): array
    {
        return $this->think ? [] : ['think' => false];
    }
}
