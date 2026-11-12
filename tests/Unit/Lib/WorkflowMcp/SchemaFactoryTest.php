<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp;

use App\Tests\Unit\Lib\WorkflowMcp\Fixture\ContactInput;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\MixedInput;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\ReviewInput;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\UnionInput;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\UntypedInput;
use Leash\WorkflowMcp\Schema\SchemaFactory;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** PLAN §7: Choice is structural (an enum); Email and Url stay server-side constraints (decisions/015). */
    public function testChoiceBecomesAnEnumWhileEmailAndUrlStayPlainStrings(): void
    {
        self::assertSame([
            'type' => 'object',
            'properties' => [
                'priority' => ['type' => 'string', 'enum' => ['low', 'high']],
                'email' => ['type' => 'string'],
                'homepage' => ['type' => 'string'],
            ],
            'required' => ['priority', 'email', 'homepage'],
            'additionalProperties' => false,
        ], (new SchemaFactory())->inputSchema(ContactInput::class));
    }

    /**
     * PLAN §7: unsupported constructs fail loudly. The platform factory turns `mixed` into `{"type": ["null"]}` and a
     * union into a property with no type, with PHP warnings at most (BUGS.md); SchemaFactory refuses them first.
     *
     * @param class-string $class
     */
    #[DataProvider('unsupportedInputs')]
    public function testUnsupportedPropertyTypesFailLoudly(string $class, string $message): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($message);

        (new SchemaFactory())->inputSchema($class);
    }

    /** @return iterable<string, array{class-string, string}> */
    public static function unsupportedInputs(): iterable
    {
        yield 'mixed' => [MixedInput::class, \sprintf('Input class %s: property "anything" has type mixed', MixedInput::class)];
        yield 'union' => [UnionInput::class, \sprintf('Input class %s: property "either" has type string|int', UnionInput::class)];
        yield 'untyped' => [UntypedInput::class, \sprintf('Input class %s: property "untyped" has no type', UntypedInput::class)];
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
