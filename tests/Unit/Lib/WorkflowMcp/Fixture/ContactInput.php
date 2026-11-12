<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Symfony\Component\Validator\Constraints as Assert;

/** Validator constraints that stay server-side (decisions/015): Choice, Email, Url. */
final readonly class ContactInput
{
    public function __construct(
        #[Assert\Choice(choices: ['low', 'high'])]
        public string $priority,
        #[Assert\Email]
        public string $email,
        #[Assert\Url(requireTld: true)]
        public string $homepage,
    ) {}
}
