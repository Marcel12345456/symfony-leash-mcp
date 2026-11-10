<?php

declare(strict_types=1);

namespace App\Agent\Message;

/** Message: let the workflow pick the next actor for this subject and run it (RunNextActorHandler). */
final readonly class RunNextActor
{
    public function __construct(
        public string $workflow,
        public string $subjectId,
    ) {}
}
