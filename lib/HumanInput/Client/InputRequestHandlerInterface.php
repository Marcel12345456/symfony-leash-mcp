<?php

declare(strict_types=1);

namespace Leash\HumanInput\Client;

/** Decides what happens when a tool call comes back `input_required` (PLAN §3.3 client side). */
interface InputRequestHandlerInterface
{
    public function handle(InputRequired $request, ToolCall $call): InputOutcome;
}
