<?php

declare(strict_types=1);

namespace Leash\WorkflowMcp\Server;

use Leash\WorkflowMcp\Event\CapabilityDenied;
use Mcp\Schema\JsonRpc\Error;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\Request\ReadResourceRequest;
use Mcp\Schema\Result\ReadResourceResult;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Per-request handler that answers `resources/read` for URIs not visible to this caller (decisions/025):
 * resources/read has no error result shape, so it is a JSON-RPC -32602 that names the readable URIs,
 * plus a CapabilityDenied event (its `tool` is the URI).
 *
 * @implements RequestHandlerInterface<ReadResourceResult>
 */
final readonly class UnavailableResourceResponder implements RequestHandlerInterface
{
    /**
     * @param list<string> $places
     * @param list<string> $visible URIs the caller may read
     */
    public function __construct(
        private string $workflow,
        private object $subject,
        private UserInterface $caller,
        private array $places,
        private array $visible,
        private EventDispatcherInterface $dispatcher,
    ) {}

    public function supports(Request $request): bool
    {
        return $request instanceof ReadResourceRequest && !\in_array($request->uri, $this->visible, true);
    }

    public function handle(Request $request, SessionInterface $session): Error
    {
        if (!$request instanceof ReadResourceRequest) {
            return Error::forInvalidParams('Expected resources/read.', $request->getId());
        }

        $this->dispatcher->dispatch(new CapabilityDenied($this->workflow, $this->subject, $this->caller, $request->uri, $this->places, $this->visible));

        return Error::forInvalidParams(
            \sprintf(
                'Resource "%s" is not available to your role in state "%s". %s',
                $request->uri,
                implode(', ', $this->places),
                [] === $this->visible ? 'No resources are readable for you now.' : 'Readable now: ' . implode(', ', $this->visible) . '.',
            ),
            $request->getId(),
            ['uri' => $request->uri, 'visible' => $this->visible],
        );
    }
}
