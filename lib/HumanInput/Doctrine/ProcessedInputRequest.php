<?php

declare(strict_types=1);

namespace Leash\HumanInput\Doctrine;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** One applied input request: the nonce is the idempotency key, the result is replayed to duplicate retries. */
#[ORM\Entity]
#[ORM\Table(name: 'processed_input_request')]
class ProcessedInputRequest
{
    /** @param array<string, mixed> $result */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 64)]
        public readonly string $nonce,
        #[ORM\Column(type: Types::JSON)]
        public readonly array $result,
        #[ORM\Column]
        public readonly \DateTimeImmutable $processedAt,
    ) {}
}
