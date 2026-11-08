<?php

declare(strict_types=1);

namespace Leash\HumanInput\RequestState;

final class RequestStateRejected extends \RuntimeException
{
    public function __construct(public readonly RejectionReason $reason)
    {
        parent::__construct($reason->message());
    }
}
