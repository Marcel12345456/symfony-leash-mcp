<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

use Symfony\Component\Validator\Constraints as Assert;

/** Synthetic input DTO covering every type SchemaFactory must map. */
final readonly class ReviewInput
{
    /**
     * @param int          $score Score in points
     * @param list<string> $tags  Free-form tags
     */
    public function __construct(
        #[Assert\Positive, Assert\Range(max: 100)]
        public int $score,
        #[Assert\NotBlank, Assert\Length(max: 50)]
        public string $summary,
        public ?string $note,
        public Verdict $verdict,
        public ?Verdict $previous = null,
        public array $tags = [],
        public bool $urgent = false,
    ) {}
}
