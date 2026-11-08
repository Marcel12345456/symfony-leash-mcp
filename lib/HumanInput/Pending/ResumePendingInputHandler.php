<?php

declare(strict_types=1);

namespace Leash\HumanInput\Pending;

use Leash\HumanInput\Client\CallerTokens;
use Leash\HumanInput\Client\InputRequired;
use Leash\HumanInput\Client\McpCallFailed;
use Leash\HumanInput\Client\RawMcpClient;
use Leash\HumanInput\Event\PendingInputResolved;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Retries the original call with the original caller's token plus answers and request state,
 * records the result and announces it. Transport errors propagate so Messenger retries;
 * a duplicate message finds the item no longer answered and does nothing.
 */
#[AsMessageHandler]
final readonly class ResumePendingInputHandler
{
    public function __construct(
        private PendingInputs $pending,
        private RawMcpClient $client,
        private CallerTokens $tokens,
        private HumanInputNotifier $notifier,
        private EventDispatcherInterface $events,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ResumePendingInput $message): void
    {
        $pending = $this->pending->get($message->pendingId);
        if (null === $pending || PendingInputStatus::Answered !== $pending->status) {
            return;
        }

        try {
            $result = $this->client->call($pending->toolCall(), $this->tokens->tokenFor($pending->callerId), $pending->responses ?? [], $pending->requestState);
        } catch (McpCallFailed $e) {
            $pending->fail($e->getMessage(), $this->clock->now());
            $this->pending->save($pending);
            $this->events->dispatch(new PendingInputResolved($pending->id, $pending->subjectRef, $pending->tool, true));

            return;
        }

        if ($result instanceof InputRequired) {
            $pending->askAgain($result);
            $this->pending->save($pending);
            $this->notifier->notify($pending);

            return;
        }

        $wire = json_decode(json_encode($result, \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($wire));
        $pending->resolve(array_combine(array_map(strval(...), array_keys($wire)), $wire), $this->clock->now());
        $this->pending->save($pending);
        $this->events->dispatch(new PendingInputResolved($pending->id, $pending->subjectRef, $pending->tool, $result->isError));
    }
}
