# Leash\WorkflowMcp

An MCP server per workflow subject whose tools and resources follow a Symfony Workflow: a caller sees what the
subject's **current place** offers to the caller's **roles**, nothing else. The workflow, not the model, decides who
acts next.

No domain code lives here (architecture tests enforce it). Werewolf and Doc Review in `src/` are two users of it.

## Endpoint

`/mcp/{workflow}/{subjectId}` (`Http\WorkflowMcpController`) serves one MCP server per request, built by
`Server\WorkflowServerFactory` for that subject and the authenticated caller. Authentication is yours (the demo
app uses a bearer-token firewall). The subject is loaded by `Subject\DoctrineSubjectProvider` from `mcp_subject`.

## Visibility rule

`Capability\ScopedCapabilityProvider` lists, for subject + caller:

- **transition tools:** every *enabled* transition (guards run) whose `mcp_roles` share a role with the caller;
- **place tools and resources:** those the current place names in `mcp_tools` / `mcp_resources` for one of the
  caller's roles.

Calls to anything else get an actionable error naming the tools that are visible now, and a `CapabilityDenied`
event; nothing changes. Results carry `structuredContent` `{state, availableTools, message}`.

## Metadata contract (workflow YAML)

```yaml
framework:
    workflows:
        doc_review:
            type: state_machine
            metadata:
                mcp_prefix: doc                                   # tool names: doc_<transition>
                mcp_subject: App\DocReview\Entity\Document        # loaded by id from the URL
                mcp_role_resolver: App\…\MyRoleResolver           # optional; default: the caller's security roles
            places:
                in_review:
                    metadata:
                        mcp_tools:     { my_place_tool: [ROLE_REVIEWER] }      # place tools by name => roles (illustrative)
                        mcp_resources: { brief: [ROLE_REVIEWER], rubric: [ROLE_REVIEWER] }
            transitions:
                publish:
                    from: approved
                    to: published
                    metadata:
                        mcp_roles: [ROLE_REVIEWER]                     # who may call doc_publish
                        mcp_description: 'Publish an approved document under a headline.'
                        mcp_input: App\DocReview\Mcp\Input\PublishInput  # DTO → input schema + validation
                        mcp_approval: editor_signoff                   # ask a human first (Leash\HumanInput)
```

## Extension points

| You write | How it is found | What it does |
|---|---|---|
| `#[AsPlaceTool(workflow, name, description, readOnly = true, input = null)]` + `PlaceTool\PlaceToolInterface` | attribute | a tool offered by a place, not a transition (`__invoke(subject, ?input, caller)`) |
| `PlaceTool\InputChoicesInterface` | on the place tool | the pick list a human gets when an argument is missing |
| `#[AsPlaceResource(workflow, name, uri, description, mimeType)]` + `PlaceResource\PlaceResourceInterface` | attribute | a resource of a place; `{subjectId}` in the URI is filled in |
| `Role\RoleResolverInterface` | `mcp_role_resolver` | roles per subject and caller (e.g. "the seat whose turn it is") |
| Input DTO with Validator constraints | `mcp_input` / `input` | published as a structural JSON schema; violations come back as actionable errors |

## Who acts next

`Capability\ActorSelector::select()` / `actorsAmong()` return the callers that have at least one visible tool that is
not read-only. Your runner asks it and plays exactly those agents (rule G3: never an LLM).

## Client side

- `Client\McpClientToolbox`: Symfony AI toolbox over an `Mcp\Client`; every call reaches the server, errors stay
  model-visible text.
- `Client\TurnBudgetToolbox`: caps tool calls per turn.
- `Client\HumanInputToolbox`: routes calls through `Leash\HumanInput` so `input_required` pauses the turn.

## Human input

`Tool\TransitionToolHandler` asks `Leash\HumanInput\Server\HumanInputGate` before applying a transition with
`mcp_approval`; `Tool\ElicitMissingInputPolicy` asks a human-backed caller for missing place-tool arguments. Both use
MCP `input_required` (MRTR), see `lib/HumanInput/README.md`.

## Tests

`tests/Unit/Lib/WorkflowMcp/` run on a synthetic, non-domain workflow (`tests/Unit/Lib/WorkflowMcp/Fixture/`),
in-process through the SDK; the HTTP endpoint is covered in `tests/Integration/Lib/`.
