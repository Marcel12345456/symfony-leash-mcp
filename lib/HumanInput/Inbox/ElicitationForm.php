<?php

declare(strict_types=1);

namespace Leash\HumanInput\Inbox;

use Mcp\Schema\Elicitation\ElicitationSchema;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Renders an elicitation `requestedSchema` (raw wire array, decisions/023) as a Symfony Form and turns
 * the submitted data into typed `content`: enum → choice, boolean → checkbox, number → number field.
 */
final readonly class ElicitationForm
{
    public function __construct(private FormFactoryInterface $forms) {}

    /**
     * @param array<mixed> $requestedSchema
     *
     * @return FormInterface<mixed>
     *
     * @throws \InvalidArgumentException when the schema is not a valid elicitation schema
     */
    public function create(array $requestedSchema, string $name = 'answer'): FormInterface
    {
        ElicitationSchema::fromArray($requestedSchema);
        $required = self::required($requestedSchema);

        $builder = $this->forms->createNamedBuilder($name, FormType::class, self::defaults($requestedSchema));
        foreach (self::properties($requestedSchema) as $field => $property) {
            [$type, $options] = self::field($property, \in_array($field, $required, true));
            $builder->add($field, $type, $options + [
                'label' => \is_string($property['title'] ?? null) ? $property['title'] : $field,
                'help' => \is_string($property['description'] ?? null) ? $property['description'] : null,
            ]);
        }

        return $builder->getForm();
    }

    /**
     * Typed answer content for `ElicitResult`; optional empty fields are left out.
     *
     * @param array<mixed>         $requestedSchema
     * @param FormInterface<mixed> $form            a submitted, valid form from create()
     *
     * @return array<string, mixed>
     */
    public function content(array $requestedSchema, FormInterface $form): array
    {
        $data = $form->getData();
        $data = \is_array($data) ? $data : [];
        $required = self::required($requestedSchema);
        $content = [];
        foreach (self::properties($requestedSchema) as $field => $property) {
            $value = $data[$field] ?? null;
            $value = match ($property['type'] ?? null) {
                'boolean' => (bool) $value,
                'integer' => \is_numeric($value) ? (int) $value : null,
                'number' => \is_int($value) || \is_float($value) ? $value : null,
                'array' => \is_array($value) && [] !== $value ? array_values($value) : null,
                default => \is_scalar($value) && '' !== (string) $value ? (string) $value : null,
            };
            if (null !== $value || \in_array($field, $required, true)) {
                $content[$field] = $value;
            }
        }

        return $content;
    }

    /**
     * @param array<string, mixed> $property
     *
     * @return array{class-string<FormTypeInterface<mixed>>, array<string, mixed>}
     */
    private static function field(array $property, bool $required): array
    {
        $notBlank = $required ? [new Assert\NotBlank()] : [];
        $length = isset($property['minLength']) || isset($property['maxLength'])
            ? [new Assert\Length(min: self::size($property['minLength'] ?? null), max: self::positive($property['maxLength'] ?? null))]
            : [];
        $range = isset($property['minimum']) || isset($property['maximum'])
            ? [new Assert\Range(min: self::number($property['minimum'] ?? null), max: self::number($property['maximum'] ?? null))]
            : [];
        $base = ['required' => $required];

        return match ($property['type'] ?? null) {
            'boolean' => [CheckboxType::class, ['required' => false]],
            'integer' => [IntegerType::class, $base + ['constraints' => [...$notBlank, ...$range]]],
            'number' => [NumberType::class, $base + ['constraints' => [...$notBlank, ...$range], 'input' => 'number']],
            'array' => [ChoiceType::class, $base + [
                'choices' => self::choices($property['items'] ?? []),
                'multiple' => true,
                'expanded' => true,
                'constraints' => isset($property['minItems']) || isset($property['maxItems'])
                    ? [new Assert\Count(min: self::size($property['minItems'] ?? null), max: self::size($property['maxItems'] ?? null))]
                    : [],
            ]],
            default => isset($property['oneOf']) || isset($property['enum'])
                ? [ChoiceType::class, $base + ['choices' => self::choices($property), 'expanded' => true, 'placeholder' => false, 'constraints' => $notBlank]]
                : match ($property['format'] ?? null) {
                    'email' => [EmailType::class, $base + ['constraints' => [...$notBlank, ...$length]]],
                    'uri' => [UrlType::class, $base + ['constraints' => [...$notBlank, ...$length], 'default_protocol' => null]],
                    'date', 'date-time' => [TextType::class, $base + [
                        'constraints' => $notBlank,
                        'attr' => ['type' => 'date' === $property['format'] ? 'date' : 'datetime-local'],
                    ]],
                    default => [TextType::class, $base + ['constraints' => [...$notBlank, ...$length], 'empty_data' => '']],
                },
        };
    }

    /**
     * Label => value for titled (`oneOf` / `anyOf` const+title) and legacy (`enum` + `enumNames`) enums.
     *
     * @return array<string, string>
     */
    private static function choices(mixed $enum): array
    {
        if (!\is_array($enum)) {
            return [];
        }
        $titled = $enum['oneOf'] ?? $enum['anyOf'] ?? null;
        if (\is_array($titled)) {
            $choices = [];
            foreach ($titled as $option) {
                if (\is_array($option) && \is_string($option['const'] ?? null)) {
                    $choices[\is_string($option['title'] ?? null) ? $option['title'] : $option['const']] = $option['const'];
                }
            }

            return $choices;
        }
        $values = array_values(array_filter(\is_array($enum['enum'] ?? null) ? $enum['enum'] : [], \is_string(...)));
        $names = \is_array($enum['enumNames'] ?? null) ? array_values($enum['enumNames']) : [];
        $choices = [];
        foreach ($values as $i => $value) {
            $choices[\is_string($names[$i] ?? null) ? $names[$i] : $value] = $value;
        }

        return $choices;
    }

    /**
     * @param array<mixed> $schema
     *
     * @return array<string, array<string, mixed>>
     */
    private static function properties(array $schema): array
    {
        $properties = [];
        foreach (\is_array($schema['properties'] ?? null) ? $schema['properties'] : [] as $name => $property) {
            if (\is_array($property)) {
                $properties[(string) $name] = array_combine(array_map(strval(...), array_keys($property)), $property);
            }
        }

        return $properties;
    }

    /**
     * @param array<mixed> $schema
     *
     * @return list<string>
     */
    private static function required(array $schema): array
    {
        return array_values(array_filter(\is_array($schema['required'] ?? null) ? $schema['required'] : [], \is_string(...)));
    }

    /**
     * @param array<mixed> $schema
     *
     * @return array<string, mixed>
     */
    private static function defaults(array $schema): array
    {
        $defaults = [];
        foreach (self::properties($schema) as $name => $property) {
            if (\array_key_exists('default', $property)) {
                $defaults[$name] = $property['default'];
            }
        }

        return $defaults;
    }

    /** @return int<1, max>|null */
    private static function positive(mixed $value): ?int
    {
        return \is_int($value) && $value >= 1 ? $value : null;
    }

    /** @return int<0, max>|null */
    private static function size(mixed $value): ?int
    {
        return \is_int($value) && $value >= 0 ? $value : null;
    }

    private static function number(mixed $value): int|float|null
    {
        return \is_int($value) || \is_float($value) ? $value : null;
    }
}
