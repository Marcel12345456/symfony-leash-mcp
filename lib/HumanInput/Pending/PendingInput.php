<?php

declare(strict_types=1);

namespace Leash\HumanInput\Pending;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Leash\HumanInput\Client\InputRequired;
use Leash\HumanInput\Client\ToolCall;

/**
 * A tool call that waits for a human (PLAN §3.3): the original call, the raw asks and request state
 * (decisions/020), the answers, and the final result once resumed. No caller token is stored.
 */
#[ORM\Entity]
#[ORM\Table(name: 'pending_input')]
#[ORM\Index(columns: ['status'])]
class PendingInput
{
    #[ORM\Id]
    #[ORM\Column(length: 32)]
    public readonly string $id;

    #[ORM\Column(length: 1024)]
    public readonly string $endpoint;

    #[ORM\Column]
    public readonly string $subjectRef;

    #[ORM\Column]
    public readonly string $callerId;

    #[ORM\Column]
    public readonly string $tool;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    public readonly array $arguments;

    /** @var array<string, array<string, mixed>> */
    #[ORM\Column(type: Types::JSON)]
    public private(set) array $inputRequests;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public private(set) ?string $requestState;

    #[ORM\Column(enumType: PendingInputStatus::class)]
    public private(set) PendingInputStatus $status = PendingInputStatus::Pending;

    /** @var array<string, array<string, mixed>>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public private(set) ?array $responses = null;

    /** @var array<string, mixed>|null the final `tools/call` result, or `{error}` when the resume was refused */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    public private(set) ?array $result = null;

    #[ORM\Column]
    public readonly \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $answeredAt = null;

    #[ORM\Column(nullable: true)]
    public private(set) ?\DateTimeImmutable $resolvedAt = null;

    public function __construct(ToolCall $call, InputRequired $request, \DateTimeImmutable $now)
    {
        $this->id = bin2hex(random_bytes(6));
        $this->endpoint = $call->endpoint;
        $this->subjectRef = $call->subjectRef;
        $this->callerId = $call->callerId;
        $this->tool = $call->tool;
        $this->arguments = $call->arguments;
        $this->inputRequests = $request->inputRequests;
        $this->requestState = $request->requestState;
        $this->createdAt = $now;
    }

    public function toolCall(): ToolCall
    {
        return new ToolCall($this->endpoint, $this->subjectRef, $this->callerId, $this->tool, $this->arguments);
    }

    /** @param array<string, array<string, mixed>> $responses key => `ElicitResult` wire shape */
    public function answer(array $responses, \DateTimeImmutable $now): void
    {
        if (PendingInputStatus::Pending !== $this->status) {
            throw new \LogicException(\sprintf('Pending input #%s is %s and cannot be answered.', $this->id, $this->status->value));
        }
        $this->responses = $responses;
        $this->answeredAt = $now;
        $this->status = PendingInputStatus::Answered;
    }

    /** The server asked again (e.g. an answer did not fit the form): wait for a human once more. */
    public function askAgain(InputRequired $request): void
    {
        $this->inputRequests = $request->inputRequests;
        $this->requestState = $request->requestState;
        $this->responses = null;
        $this->answeredAt = null;
        $this->status = PendingInputStatus::Pending;
    }

    /** @param array<string, mixed> $result */
    public function resolve(array $result, \DateTimeImmutable $now): void
    {
        $this->result = $result;
        $this->resolvedAt = $now;
        $this->status = PendingInputStatus::Resolved;
    }

    public function fail(string $error, \DateTimeImmutable $now): void
    {
        $this->result = ['error' => $error];
        $this->resolvedAt = $now;
        $this->status = PendingInputStatus::Failed;
    }
}
