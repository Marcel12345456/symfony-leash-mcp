<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Server;

use Leash\HumanInput\RequestState\RequestStateRejected;
use Leash\HumanInput\Server\HumanInputGate;
use Leash\WorkflowMcp\Event\CapabilityDenied;
use Leash\WorkflowMcp\Tool\ToolResults;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;
use Mcp\Server\Stateless\InputContext;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Per-request handler that answers `tools/call` for names not visible to this caller (decisions/010):
 * an actionable CallToolResult error instead of the SDK's JSON-RPC -32602, plus a CapabilityDenied event.
 * A duplicate MRTR retry of an already applied call gets its stored result instead: the transition it applied
 * is usually no longer visible. The SDK keeps the verified InputContext in the session under its class name.
 *
 * @implements RequestHandlerInterface<CallToolResult>
 */
final readonly class UnavailableCapabilityResponder implements RequestHandlerInterface
{
    /**
     * @param list<string> $places
     * @param list<string> $visible
     */
    public function __construct(
        private string $workflow,
        private object $subject,
        private UserInterface $caller,
        private array $places,
        private array $visible,
        private EventDispatcherInterface $dispatcher,
        private HumanInputGate $humanInput,
        private string $subjectRef,
    ) {}

    public function supports(Request $request): bool
    {
        return $request instanceof CallToolRequest && !\in_array($request->name, $this->visible, true);
    }

    public function handle(Request $request, SessionInterface $session): Response|Error
    {
        if (!$request instanceof CallToolRequest) {
            return Error::forInvalidParams('Expected tools/call.', $request->getId());
        }

        $replay = $this->replay($session->get(InputContext::class), $request);
        if (null !== $replay) {
            return new Response($request->getId(), ToolResults::fromStored($replay));
        }

        $this->dispatcher->dispatch(new CapabilityDenied($this->workflow, $this->subject, $this->caller, $request->name, $this->places, $this->visible));

        return new Response($request->getId(), ToolResults::unavailable($request->name, $this->places, $this->visible));
    }

    /** @return array<string, mixed>|null */
    private function replay(mixed $input, CallToolRequest $request): ?array
    {
        if (!$input instanceof InputContext) {
            return null;
        }
        try {
            $bound = $this->humanInput->boundRetry($input, $this->subjectRef, $request->name, $request->arguments, $this->caller->getUserIdentifier());
        } catch (RequestStateRejected) {
            return null;
        }

        return null === $bound ? null : $this->humanInput->replay($bound);
    }
}
