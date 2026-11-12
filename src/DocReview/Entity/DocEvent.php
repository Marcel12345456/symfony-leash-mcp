<?php

declare(strict_types=1);

namespace App\DocReview\Entity;

use Doctrine\ORM\Mapping as ORM;

/** What happened to a document, for the dashboard (PLAN §3.6, §3.7). */
#[ORM\Entity]
class DocEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    /** @param array<string, mixed> $payload */
    public function __construct(
        #[ORM\Column(length: 32)]
        public readonly string $documentId,
        #[ORM\Column(length: 32, enumType: DocEventType::class)]
        public readonly DocEventType $type,
        #[ORM\Column(length: 64)]
        public readonly string $actor,
        #[ORM\Column(type: 'json')]
        public readonly array $payload,
        #[ORM\Column(type: 'datetime_immutable')]
        public readonly \DateTimeImmutable $at = new \DateTimeImmutable(),
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }
}
