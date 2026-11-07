<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\HumanInput\Fixture;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class RecordingBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $messages = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->messages[] = $message;

        return Envelope::wrap($message, $stamps);
    }
}
