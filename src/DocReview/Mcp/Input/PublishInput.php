<?php

declare(strict_types=1);

namespace App\DocReview\Mcp\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class PublishInput
{
    /** @param string $headline Headline to publish under (the editor may change it) */
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 200)]
        public string $headline,
    ) {}
}
