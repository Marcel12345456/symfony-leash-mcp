<?php

declare(strict_types=1);

namespace App\Tests\Unit\Lib\HumanInput;

use Leash\HumanInput\Inbox\ElicitationForm;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Validator\Validation;

/** decisions/023: every elicitation property shape becomes a field, and answers come back with JSON types. */
final class ElicitationFormTest extends TestCase
{
    private const array SCHEMA = [
        'type' => 'object',
        'properties' => [
            'seat' => ['type' => 'string', 'title' => 'Vote', 'oneOf' => [['const' => '2', 'title' => 'Ben (seat 2)'], ['const' => '5', 'title' => 'Eve (seat 5)']]],
            'color' => ['type' => 'string', 'enum' => ['red', 'blue'], 'enumNames' => ['Red', 'Blue']],
            'urgent' => ['type' => 'boolean', 'default' => true],
            'count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
            'ratio' => ['type' => 'number'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['a', 'b', 'c']]],
            'note' => ['type' => 'string', 'maxLength' => 10],
        ],
        'required' => ['seat', 'count'],
    ];

    public static function form(): ElicitationForm
    {
        return new ElicitationForm(Forms::createFormFactoryBuilder()->addExtension(new ValidatorExtension(Validation::createValidator()))->getFormFactory());
    }

    public function testEnumsBecomeChoicesWithTitlesAsLabels(): void
    {
        $form = self::form()->create(self::SCHEMA);

        self::assertInstanceOf(ChoiceType::class, $form->get('seat')->getConfig()->getType()->getInnerType());
        self::assertSame(['Ben (seat 2)' => '2', 'Eve (seat 5)' => '5'], $form->get('seat')->getConfig()->getOption('choices'));
        self::assertSame('Vote', $form->get('seat')->getConfig()->getOption('label'));
        self::assertSame(['Red' => 'red', 'Blue' => 'blue'], $form->get('color')->getConfig()->getOption('choices'));
        self::assertTrue($form->get('tags')->getConfig()->getOption('multiple'));
        self::assertInstanceOf(CheckboxType::class, $form->get('urgent')->getConfig()->getType()->getInnerType());
        self::assertInstanceOf(IntegerType::class, $form->get('count')->getConfig()->getType()->getInnerType());
        self::assertTrue($form->get('urgent')->getData(), 'defaults pre-fill the form');
    }

    public function testAnswersKeepJsonTypesAndOmitEmptyOptionalFields(): void
    {
        $form = self::form()->create(self::SCHEMA);
        $form->submit(['seat' => '5', 'count' => '3', 'ratio' => '0.5', 'tags' => ['a', 'c'], 'note' => '']);

        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(['seat' => '5', 'urgent' => false, 'count' => 3, 'ratio' => 0.5, 'tags' => ['a', 'c']], self::form()->content(self::SCHEMA, $form));
    }

    public function testValuesOutsideTheSchemaAreInvalid(): void
    {
        foreach ([['seat' => '9', 'count' => '3'], ['seat' => '2', 'count' => '7'], ['seat' => '2'], ['seat' => '2', 'count' => '1', 'note' => str_repeat('x', 11)]] as $data) {
            $form = self::form()->create(self::SCHEMA);
            $form->submit($data);
            self::assertFalse($form->isValid(), json_encode($data, \JSON_THROW_ON_ERROR));
        }
    }

    public function testUnknownPropertyTypesFailLoudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::form()->create(['type' => 'object', 'properties' => ['x' => ['type' => 'object']]]);
    }
}
