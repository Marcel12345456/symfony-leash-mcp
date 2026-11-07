<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\HumanInput\Fixture;

use Leash\HumanInput\Pending\HumanInputNotifier;
use Leash\HumanInput\Pending\PendingInput;

final class RecordingNotifier implements HumanInputNotifier
{
    /** @var list<string> pending ids */
    public array $notified = [];

    public function notify(PendingInput $pending): void
    {
        $this->notified[] = $pending->id;
    }
}
