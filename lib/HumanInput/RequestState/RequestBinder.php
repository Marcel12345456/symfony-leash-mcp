<?php

declare(strict_types=1);

namespace Leash\HumanInput\RequestState;

use Psr\Clock\ClockInterface;

/**
 * Issues and checks the payload bound into an MRTR request state.
 *
 * The SDK codec signs the state and verifies its MAC before the handler runs (decisions/019);
 * this class checks that the verified payload belongs to the current call.
 */
final readonly class RequestBinder
{
    public const int DEFAULT_TTL = 86400;

    public function __construct(private ClockInterface $clock) {}

    /** @param array<mixed> $arguments */
    public function issue(string $subjectRef, string $capability, array $arguments, string $callerId, int $ttlSeconds = self::DEFAULT_TTL): BoundRequest
    {
        return new BoundRequest(
            $subjectRef,
            $capability,
            ArgumentsHash::of($arguments),
            $callerId,
            bin2hex(random_bytes(16)),
            $this->clock->now()->getTimestamp() + $ttlSeconds,
        );
    }

    /**
     * @param array<mixed> $payload   the verified payload from `InputContext::requestState()`
     * @param array<mixed> $arguments the arguments of the retry
     *
     * @throws RequestStateRejected
     */
    public function verify(array $payload, string $subjectRef, string $capability, array $arguments, string $callerId): BoundRequest
    {
        $bound = BoundRequest::fromArray($payload) ?? throw new RequestStateRejected(RejectionReason::Tampered);

        if ($bound->expiresAt <= $this->clock->now()->getTimestamp()) {
            throw new RequestStateRejected(RejectionReason::Expired);
        }
        if ($bound->subjectRef !== $subjectRef || $bound->capability !== $capability) {
            throw new RequestStateRejected(RejectionReason::WrongTarget);
        }
        if (!hash_equals($bound->callerId, $callerId)) {
            throw new RequestStateRejected(RejectionReason::WrongCaller);
        }
        if (!hash_equals($bound->argsHash, ArgumentsHash::of($arguments))) {
            throw new RequestStateRejected(RejectionReason::ArgumentsMismatch);
        }

        return $bound;
    }
}
