<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp;

use App\Tests\Unit\Lib\WorkflowMcp\Fixture\ReviewInput;
use Leash\WorkflowMcp\Schema\SchemaFactory;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use PHPUnit\Framework\TestCase;

/** Pins the decisions/013 + 015 output: structural keywords only, nullable fields optional, nullable enums accept null. */
final class SchemaFactoryTest extends TestCase
{
    public function testInputSchemaFromDto(): void
    {
        $schema = json_decode((string) json_encode((new SchemaFactory())->inputSchema(ReviewInput::class)), true);

        self::assertSame([
            'type' => 'object',
            'properties' => [
                'score' => ['type' => 'integer', 'description' => 'Score in points'],
                'summary' => ['type' => 'string'],
                'note' => ['type' => ['string', 'null']],
                'verdict' => ['type' => 'string', 'enum' => ['keep', 'drop']],
                'previous' => ['type' => ['string', 'null'], 'enum' => ['keep', 'drop', null]],
                'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Free-form tags'],
                'urgent' => ['type' => 'boolean'],
            ],
            'required' => ['score', 'summary', 'verdict'],
            'additionalProperties' => false,
        ], $schema);
    }

    /** Repro for the BUGS.md entries: what the platform factory emits before SchemaFactory normalizes it. */
    public function testPlatformFactoryRawOutputNeedsTheNormalizer(): void
    {
        $raw = (new Factory())->buildProperties(ReviewInput::class);
        self::assertIsArray($raw);
        self::assertContains('note', $raw['required']);
        self::assertSame(['type' => ['string', 'null'], 'enum' => ['keep', 'drop']], $raw['properties']['previous']);
        self::assertTrue($raw['properties']['score']['exclusiveMinimum'] ?? null);
    }

    public function testNoInputClassGivesEmptyObjectSchema(): void
    {
        self::assertSame('{"type":"object","properties":{}}', json_encode((new SchemaFactory())->inputSchema(null)));
    }

    public function testTransitionOutputSchemaIsAnObjectWithTheResultFields(): void
    {
        $schema = (new SchemaFactory())->transitionOutputSchema();

        self::assertSame('object', $schema['type']);
        self::assertSame(['state', 'availableTools', 'message'], $schema['required']);
    }
}
