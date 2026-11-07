<?php

declare(strict_types=1);

namespace Leash\HumanInput\Pending;

use Mcp\Schema\Result\ElicitResult;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/** Records a human's answer and queues the resume (inbox, email link, Telegram all end here). */
final readonly class PendingInputAnswers
{
    public function __construct(
        private PendingInputs $pending,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {}

    /**
     * @param array<string, ElicitResult> $answers input request key => answer
     *
     * @throws \OutOfBoundsException for an unknown id
     * @throws \LogicException       when the item is no longer waiting
     */
    public function answer(string $pendingId, array $answers): void
    {
        $pending = $this->pending->get($pendingId) ?? throw new \OutOfBoundsException(\sprintf('No pending input #%s.', $pendingId));
        $pending->answer(array_map(static fn(ElicitResult $a): array => self::wire($a), $answers), $this->clock->now());
        $this->pending->save($pending);

        $this->bus->dispatch(new ResumePendingInput($pending->id));
    }

    /** @return array<string, mixed> */
    private static function wire(ElicitResult $answer): array
    {
        $wire = json_decode(json_encode($answer, \JSON_THROW_ON_ERROR), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($wire));
        // A form accept always carries content, even when empty (decisions/020).
        if ($answer->isAccepted()) {
            $wire['content'] = (object) ($answer->content ?? []);
        }

        return array_combine(array_map(strval(...), array_keys($wire)), $wire);
    }
}
