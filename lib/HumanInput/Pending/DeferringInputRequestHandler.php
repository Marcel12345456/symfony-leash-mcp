<?php

declare(strict_types=1);

namespace Leash\HumanInput\Pending;

use Leash\HumanInput\Client\InputOutcome;
use Leash\HumanInput\Client\InputRequestHandlerInterface;
use Leash\HumanInput\Client\InputRequired;
use Leash\HumanInput\Client\ToolCall;
use Psr\Clock\ClockInterface;

/** Parks the call as a `PendingInput` and notifies a human; the call resumes when the human answers. */
final readonly class DeferringInputRequestHandler implements InputRequestHandlerInterface
{
    public function __construct(
        private PendingInputs $pending,
        private HumanInputNotifier $notifier,
        private ClockInterface $clock,
    ) {}

    public function handle(InputRequired $request, ToolCall $call): InputOutcome
    {
        $pending = new PendingInput($call, $request, $this->clock->now());
        $this->pending->save($pending);
        $this->notifier->notify($pending);

        return InputOutcome::deferred($pending->id);
    }
}
