<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp\Fixture;

/** An input DTO SchemaFactory must refuse: no JSON schema type describes this property. */
final class UntypedInput
{
    /** @phpstan-ignore missingType.parameter (the point of this fixture) */
    public function __construct(public $untyped = null) {}
}
