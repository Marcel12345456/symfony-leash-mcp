# 023 — Elicitation `requestedSchema` shapes and the inbox form mapping

- Date: 2026-11-07
- Status: accepted
- PLAN reference: §3.3 HumanInput inbox ("renders each pending item's elicitation schema as a Symfony Form"); M2 acceptance "enum schema renders as a choice"

## Question

What can an elicitation `requestedSchema` contain in mcp/sdk 0.9.0, and who validates accepted `content`?

## Answer

Paths under `vendor/mcp/sdk/src/Schema/` (v0.9.0 per `composer.lock`).

- Envelope `Elicitation/ElicitationSchema.php`: `type` optional but must be `object` (:58); `properties` required, non-empty (:33, :62); `required` optional, names must exist (:37-44, :74). Dispatch by property `type`; unknown or missing type throws `InvalidArgumentException` (:91-101). `string`+`oneOf` → titled enum, `string`+`enum` → legacy enum, other `string` → string (:107-118); `array` needs `items.anyOf` or `items.enum` (:123-134). `getDefaults()` (:141).
- All property classes extend `AbstractSchemaDefinition` (optional `title`, `description`).

| Wire shape | Class | Content type | Form mapping |
|---|---|---|---|
| `{type:string, format?: date\|date-time\|email\|uri, minLength?, maxLength?, default?}` | `StringSchemaDefinition` | string | `TextType` (+ `Length`); formats as text input with matching HTML5 type, ISO strings |
| `{type:integer\|number, minimum?, maximum?, default?}` | `NumberSchemaDefinition` (`integerOnly` for integer, :75) | int / float | `IntegerType` / `NumberType` + `Range` |
| `{type:boolean, default?}` | `BooleanSchemaDefinition` | bool | `CheckboxType`, unchecked → `false` |
| `{type:string, enum:[…], enumNames?, default?}` (legacy) | `EnumSchemaDefinition` | string | `ChoiceType`, label `enumNames[i]` |
| `{type:string, oneOf:[{const,title}], default?}` | `TitledEnumSchemaDefinition` | string (const) | `ChoiceType`, label = title |
| `{type:array, items:{type:string, enum}, minItems?, maxItems?}` | `MultiSelectEnumSchemaDefinition` | list<string> | `ChoiceType(multiple, expanded)` + `Count` |
| `{type:array, items:{anyOf:[{const,title}]}}` | `TitledMultiSelectEnumSchemaDefinition` | list<string> | same, titles as labels |

Only string enum values exist (numeric enums throw).

- **No content validation in the SDK.** `ElicitResult::fromArray` checks only `action` and that a form accept has array `content` (`Result/ElicitResult.php:49-67`); `InputContext::response` returns the raw answer (`Server/Stateless/InputContext.php:36-60`). Types, enum membership and required fields are the handler's job.
- Our code today emits only string (test fixture `ApproveApproval`) and boolean (spike) schemas.

## Decision

- `lib/HumanInput/Inbox/ElicitationForm` (generic): parse the stored `requestedSchema` with `ElicitationSchema::fromArray` (unknown types fail loudly), build a Symfony Form per the table with defaults and constraints from the schema, convert submitted data to typed `content` (bool, int/float, string, list). Optional empty values are omitted.
- The form's constraints are the check on the client side; the server side keeps its own check in `applyAnswer()` (policies already validate what they fold in). No separate content validator until a second use needs it.
- Round-trip test uses a synthetic titled-enum policy (enum → choice).

## Consequences

- Not a bug: the spec leaves content validation to the server. Recorded as an observation, no BUGS.md entry.
- Inbox handles all seven shapes; tests cover enum (titled + legacy), boolean, integer, string and multi-select type conversion.
