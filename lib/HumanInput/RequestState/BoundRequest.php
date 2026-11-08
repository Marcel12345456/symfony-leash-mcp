<?php

declare(strict_types=1);

namespace Leash\HumanInput\RequestState;

/**
 * The payload a request state binds: which call on which subject, by whom, once (nonce), until when.
 */
final readonly class BoundRequest
{
    public function __construct(
        public string $subjectRef,
        public string $capability,
        public string $argsHash,
        public string $callerId,
        public string $nonce,
        public int $expiresAt,
    ) {}

    /** @return array{subjectRef: string, capability: string, argsHash: string, callerId: string, nonce: string, expiresAt: int} */
    public function toArray(): array
    {
        return [
            'subjectRef' => $this->subjectRef,
            'capability' => $this->capability,
            'argsHash' => $this->argsHash,
            'callerId' => $this->callerId,
            'nonce' => $this->nonce,
            'expiresAt' => $this->expiresAt,
        ];
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): ?self
    {
        foreach (['subjectRef', 'capability', 'argsHash', 'callerId', 'nonce'] as $key) {
            if (!\is_string($data[$key] ?? null)) {
                return null;
            }
        }
        if (!\is_int($data['expiresAt'] ?? null)) {
            return null;
        }

        return new self($data['subjectRef'], $data['capability'], $data['argsHash'], $data['callerId'], $data['nonce'], $data['expiresAt']);
    }
}
