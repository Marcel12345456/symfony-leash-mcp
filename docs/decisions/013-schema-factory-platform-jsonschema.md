# 013 — SchemaFactory: reuse Platform JsonSchema Factory for DTO input schemas

- Date: 2026-11-07
- Status: proposed (derived by code reading; flip to accepted once `SchemaFactoryTest` pins the output)
- PLAN reference: §3.2 WorkflowMcp `SchemaFactory`; builds on decisions 005 and 011

## Question

Can an existing factory in symfony/ai-platform 0.15.0 or mcp/sdk 0.9.0 build a tool inputSchema from a DTO class, and can symfony/serializer 8.1 build the DTO back from the arguments with usable errors?

## Answer

**mcp/sdk: no.** `Capability/Discovery/SchemaGenerator::generate(\Reflector)` throws `BadMethodCallException` for `ReflectionClass` (`SchemaGenerator.php:65-67`); `#[Schema]` targets methods/parameters only (`Attribute/Schema.php:48`).

**symfony/ai-platform: yes.** `Symfony\AI\Platform\Contract\JsonSchema\Factory::buildProperties(string $className, array $context = []): ?array` (`Contract/JsonSchema/Factory.php:68`), final, no required ctor args. Default describer chain: Serializer, TypeInfo, Method, PropertyInfo, ValidatorConstraints, SchemaAttribute (`Describer/Describer.php:33-45`).
- Types (`TypeInfoDescriber.php:62-181`): int→integer, float→number, bool→boolean, string→string, `list<string>`→array/items, backed enum→`{type, enum}`, nested objects recurse.
- Nullable `?T` → `type: ["T","null"]`; nullable enum keeps an `enum` list without `null` (likely bug, `:70-76`, `:98-102`).
- Required = constructor params without default; nullability ignored (`Subject/PropertySubject.php:56-67`).
- Descriptions from property phpdoc; `Contract\JsonSchema\Attribute\Schema` overrides keywords.
- Validator (`ValidatorConstraintsDescriber.php:35-146`): Length→min/maxLength, Range→minimum/maximum, GreaterThan(OrEqual)/Positive→minimum (+ boolean `exclusiveMinimum: true`), Choice→enum, Count→min/maxItems, Email/Url/Uuid→format, Regex→pattern, NotBlank→`nullable` + `minLength: 1`.
- Root: `type: object`, `properties`, `required`, `additionalProperties: false`; no properties → `null` (`Describer.php:66-80`).

**Serializer:** ObjectNormalizer fills promoted readonly ctor params; needs `BackedEnumNormalizer` for enums. With `COLLECT_DENORMALIZATION_ERRORS`, a `PartialDenormalizationException` (`getNotNormalizableValueErrors()`, each with `getPath()`, `getExpectedTypes()`, `getCurrentType()`) is thrown at the end; without it, `MissingConstructorArgumentsException` / `NotNormalizableValueException` immediately. No scalar coercion unless `DISABLE_TYPE_ENFORCEMENT`. Missing nullable ctor arg → `null`.

## Decision

`SchemaFactory` wraps `Factory::buildProperties($dtoClass)` and normalizes the result in one clearly marked method:
- drop `nullable`; boolean `exclusiveMinimum` → numeric 2020-12 form;
- remove nullable fields from `required`; add `null` to a nullable enum's `enum`;
- `null` result → `{type: object, properties: {}}` (stdClass properties so it encodes as `{}`).

Denormalize with `Serializer([BackedEnumNormalizer, ObjectNormalizer])` + `COLLECT_DENORMALIZATION_ERRORS`; format one actionable line per field. Then validate with Symfony Validator (decision 011: the handler re-checks so messages stay actionable).

## Consequences

- PLAN §3.2 wording: SchemaFactory wraps Platform `Factory::buildProperties` + normalizer.
- Unit tests: DTO with int/string/?string/enum/list; exclusiveMinimum conversion; nullable enum accepts null.
- BUGS.md candidates once reproduced: nullable enum without `null`; boolean `exclusiveMinimum`/`nullable` emitted.
