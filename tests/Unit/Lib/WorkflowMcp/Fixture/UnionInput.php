<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

/** An input DTO SchemaFactory must refuse: no JSON schema type describes this property. */
final readonly class UnionInput
{
    public function __construct(public int|string $either) {}
}
