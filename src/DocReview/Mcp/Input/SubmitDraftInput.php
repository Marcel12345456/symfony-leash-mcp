<?php

declare(strict_types=1);

namespace App\DocReview\Mcp\Input;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SubmitDraftInput
{
    /**
     * @param string $title Title of the document
     * @param string $body  Full text of the document (Markdown)
     */
    public function __construct(
        #[Assert\NotBlank, Assert\Length(max: 200)]
        public string $title,
        #[Assert\NotBlank, Assert\Length(max: 6000)]
        public string $body,
    ) {}
}
