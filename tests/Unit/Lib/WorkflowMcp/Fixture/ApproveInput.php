<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ApproveInput
{
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 200)]
        public string $note,
    ) {}
}
