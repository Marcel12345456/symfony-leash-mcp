<?php

declare(strict_types=1);

namespace Leash\HumanInput\Pending;

use Psr\Log\LoggerInterface;

/** Default channel until the inbox and email arrive: the inbox lists pending inputs on its own, this logs them. */
final readonly class LoggingHumanInputNotifier implements HumanInputNotifier
{
    public function __construct(private LoggerInterface $logger) {}

    public function notify(PendingInput $pending): void
    {
        $this->logger->notice('Human input requested: #{id} for {tool} on {subject} by {caller}.', [
            'id' => $pending->id,
            'tool' => $pending->tool,
            'subject' => $pending->subjectRef,
            'caller' => $pending->callerId,
        ]);
    }
}
