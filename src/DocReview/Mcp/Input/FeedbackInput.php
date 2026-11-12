<?php

declare(strict_types=1);

namespace App\DocReview\Mcp\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class FeedbackInput
{
    /** @param string $feedback What the author must change, point by point */
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 1000)]
        public string $feedback,
    ) {}
}
