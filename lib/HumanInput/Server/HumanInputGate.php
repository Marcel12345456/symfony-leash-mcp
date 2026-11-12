<?php

declare(strict_types=1);

namespace Leash\HumanInput\Server;

use Leash\HumanInput\Idempotency\ProcessedInputRequests;
use Leash\HumanInput\Policy\ApprovalPolicyInterface;
use Leash\HumanInput\Policy\DecliningApprovalPolicyInterface;
use Leash\HumanInput\RequestState\BoundRequest;
use Leash\HumanInput\RequestState\RequestBinder;
use Leash\HumanInput\RequestState\RequestStateRejected;
use Mcp\Schema\Result\InputRequiredResult;
use Mcp\Server\RequestContext;
use Mcp\Server\Stateless\InputContext;

/**
 * Server-side MRTR steps for one tool call (PLAN §3.3): ask first, verify the retry, apply once per nonce.
 * The caller supplies the subject reference, capability (tool name) and caller id the state is bound to.
 */
final readonly class HumanInputGate
{
    public const string KEY = 'approval';

    public function __construct(
        private RequestBinder $binder,
        private ProcessedInputRequests $processed,
    ) {}

    /**
     * The verified binding of a retry, or null on a first call.
     *
     * @param array<mixed> $arguments
     *
     * @throws RequestStateRejected
     */
    public function boundRetry(?InputContext $input, string $subjectRef, string $capability, array $arguments, string $callerId): ?BoundRequest
    {
        return null === $input ? null : $this->binder->verify($input->requestState(), $subjectRef, $capability, $arguments, $callerId);
    }

    /** @return array<string, mixed>|null the result of an already applied retry */
    public function replay(BoundRequest $bound): ?array
    {
        return $this->processed->find($bound->nonce);
    }

    /** @param array<mixed> $arguments */
    public function decide(?RequestContext $context, ?BoundRequest $bound, ApprovalPolicyInterface $policy, object $subject, object $input, string $subjectRef, string $capability, array $arguments, string $callerId): Decision
    {
        if (null === $bound) {
            $request = $policy->inputRequest($subject, $input);
            if (null === $request) {
                return Decision::proceed($input, null);
            }
            if (null === $context) {
                return Decision::unanswered();
            }
            $issued = $this->binder->issue($subjectRef, $capability, $arguments, $callerId);

            return Decision::inputRequired(new InputRequiredResult([self::KEY => $request], $context->mintRequestState($issued->toArray())));
        }

        $answer = $context?->getInputContext()?->elicitResult(self::KEY);
        $reason = null !== $answer && $answer->isAccepted() && $policy instanceof DecliningApprovalPolicyInterface ? $policy->declineReason($answer->content ?? []) : null;

        return match (true) {
            null === $answer, $answer->isCancelled() => Decision::unanswered(),
            $answer->isDeclined() => Decision::declined(),
            null !== $reason => Decision::declined($reason, $bound->nonce),
            default => Decision::proceed($policy->applyAnswer($input, $answer->content ?? []), $bound->nonce),
        };
    }

    /**
     * Runs the apply once per nonce; without a nonce (no human involved) it simply runs.
     *
     * @param callable(): array<string, mixed> $apply
     *
     * @return array<string, mixed>
     */
    public function apply(?string $nonce, callable $apply): array
    {
        return null === $nonce ? $apply() : $this->processed->once($nonce, $apply)->result;
    }
}
