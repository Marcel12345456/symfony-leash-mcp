<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RateInput
{
    /**
     * @param int    $stars   How good the document is, 1 to 5
     * @param string $summary One sentence on why
     * @param string $note    Anything else (optional)
     */
    public function __construct(
        #[Assert\Range(min: 1, max: 5)]
        public int $stars,
        public string $summary,
        public string $note = '',
    ) {}
}
