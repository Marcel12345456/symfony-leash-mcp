<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\WorkflowMcp;

use App\Tests\Unit\Lib\WorkflowMcp\Fixture\ContactInput;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\ReviewInput;
use App\Tests\Unit\Lib\WorkflowMcp\Fixture\Verdict;
use Leash\WorkflowMcp\Tool\InputMapper;
use Leash\WorkflowMcp\Tool\InvalidInputException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validation;

final class InputMapperTest extends TestCase
{
    public static function mapper(): InputMapper
    {
        return new InputMapper(
            new Serializer([new BackedEnumNormalizer(), new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader()), propertyTypeExtractor: new ReflectionExtractor())]),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
        );
    }

    public function testMapsValidArguments(): void
    {
        $input = self::mapper()->map(ReviewInput::class, ['score' => 7, 'summary' => 'ok', 'verdict' => 'drop', 'tags' => ['a']]);

        self::assertInstanceOf(ReviewInput::class, $input);
        self::assertSame(7, $input->score);
        self::assertNull($input->note);
        self::assertSame(Verdict::Drop, $input->verdict);
        self::assertSame(['a'], $input->tags);
    }

    public function testNoInputClassMapsToNull(): void
    {
        self::assertNull(self::mapper()->map(null, ['ignored' => true]));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidArguments(): iterable
    {
        yield 'missing field' => [['summary' => 'ok', 'verdict' => 'keep'], 'score'];
        yield 'wrong type' => [['score' => 'seven', 'summary' => 'ok', 'verdict' => 'keep'], 'score: expected int'];
        yield 'unknown enum value' => [['score' => 7, 'summary' => 'ok', 'verdict' => 'maybe'], 'verdict'];
        yield 'unknown field' => [['score' => 7, 'summary' => 'ok', 'verdict' => 'keep', 'bogus' => 1], 'Unknown fields: bogus.'];
        yield 'constraint: blank' => [['score' => 7, 'summary' => '', 'verdict' => 'keep'], 'summary: This value should not be blank.'];
        yield 'constraint: range' => [['score' => 500, 'summary' => 'ok', 'verdict' => 'keep'], 'score: This value should be 100 or less.'];
    }

    /** @param array<string, mixed> $arguments */
    #[DataProvider('invalidArguments')]
    public function testInvalidArgumentsGiveFieldMessages(array $arguments, string $expected): void
    {
        try {
            self::mapper()->map(ReviewInput::class, $arguments);
            self::fail('Expected InvalidInputException.');
        } catch (InvalidInputException $e) {
            self::assertStringContainsString($expected, implode(' ', $e->violations));
        }
    }

    /** Choice, Email and Url are not in the published schema (decisions/015); the Validator checks them here. */
    public function testChoiceEmailAndUrlAreCheckedServerSide(): void
    {
        try {
            self::mapper()->map(ContactInput::class, ['priority' => 'urgent', 'email' => 'not-an-email', 'homepage' => 'ftp:/nope']);
            self::fail('Expected InvalidInputException.');
        } catch (InvalidInputException $e) {
            $violations = implode(' ', $e->violations);
            self::assertStringContainsString('priority: The value you selected is not a valid choice.', $violations);
            self::assertStringContainsString('email: This value is not a valid email address.', $violations);
            self::assertStringContainsString('homepage: This value is not a valid URL.', $violations);
        }

        $valid = self::mapper()->map(ContactInput::class, ['priority' => 'low', 'email' => 'ada@example.org', 'homepage' => 'https://example.org']);
        self::assertSame('ada@example.org', $valid?->email);
    }
}
