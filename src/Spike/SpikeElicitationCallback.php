<?php

declare(strict_types=1);

namespace App\Spike;

use Mcp\Client\Handler\Request\ElicitationCallbackInterface;
use Mcp\Schema\Enum\ElicitAction;
use Mcp\Schema\Request\ElicitRequest;
use Mcp\Schema\Result\ElicitResult;

/** Accepts every elicitation and records what was asked, so the command can prove the round-trip happened. */
final class SpikeElicitationCallback implements ElicitationCallbackInterface
{
    public ?string $askedMessage = null;

    public function __invoke(ElicitRequest $request): ElicitResult
    {
        $this->askedMessage = $request->message;

        return new ElicitResult(ElicitAction::Accept, ['ok' => true]);
    }
}
